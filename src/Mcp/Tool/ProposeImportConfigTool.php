<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool;

use function array_key_exists;
use function bin2hex;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigSubjectHandler;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalWidgetEmitter;
use Pimcore\Bundle\PimcoreAgentBundle\Security\BoundSessionReferenceResolver;
use Pimcore\Bundle\PimcoreAgentBundle\Service\AgentSessionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function random_bytes;
use function sprintf;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Proposes a change to an import configuration instead of writing it.
 *
 * The configuration rides a change set the user reviews field by field, so a dropped mapping
 * is seen before the next import runs without it. Registered only when both an MCP host and
 * the Change Control bundle are installed — without the latter there is no lane to propose
 * onto and the direct-write tools are the only ones that make sense.
 *
 * @internal
 */
final readonly class ProposeImportConfigTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'propose_import_config';

    private const string PROPOSAL_TYPE = 'subject-update';

    public function __construct(
        private AgentSessionServiceInterface $sessionService,
        private ProposalWidgetEmitter $widgetEmitter,
        private BoundSessionReferenceResolver $boundSessionReferenceResolver,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
        private ImportConfigVocabulary $vocabulary,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Propose Import Configuration Changes',
        description: 'Propose changes to an existing Data Importer configuration for user approval. '
            . 'Does NOT write — the configuration rides a change set the user reviews field by field. '
            . 'Read the current configuration first with get_import_config and send the COMPLETE '
            . 'document back with your changes applied; anything you leave out keeps its current '
            . 'value. Returns {proposalId, name}. The review widget is shown automatically once the '
            . 'proposal succeeds; end your turn after a successful proposal and wait for the decision.',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: false,
        )
    )]
    public function execute(
        #[Schema(type: 'string', description: 'Name of the existing configuration to change.')]
        string $name,
        #[Schema(
            type: 'string',
            description: 'The configuration as JSON or YAML — the general, loaderConfig, '
                . 'interpreterConfig, resolverConfig, processingConfig, mappingConfig and '
                . 'executionConfig sections, with your changes applied.'
        )]
        array|string $configuration,
        #[Schema(type: 'string', description: 'One sentence saying what the change does, shown on the review card.')]
        ?string $summary = null,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $sessionId = $this->boundSessionReferenceResolver->resolve();
            if ($sessionId === null) {
                return $this->errorResult('No chat session context.');
            }

            $existing = $this->load($name);
            if ($existing === null) {
                return $this->errorResult(sprintf(
                    'No import configuration named "%s". Only an existing one can be proposed against; '
                    . 'list them with list_import_configs.',
                    $name,
                ));
            }

            $proposed = $this->decode($configuration);
            if ($proposed === null) {
                return $this->errorResult('The configuration must be an object, or a JSON or YAML object string.');
            }

            $stored = $existing->getConfiguration();

            $unknown = ProposedImportConfiguration::unknownSections($proposed, $stored);
            if ($unknown !== []) {
                return $this->errorResult(sprintf(
                    '%s is not part of an import configuration. Read the current document with '
                    . 'get_import_config and send that back with your changes applied, rather than '
                    . 'writing one from memory.',
                    implode(', ', $unknown),
                ));
            }

            if (array_key_exists('mappingConfig', $proposed)) {
                $mappings = ProposedImportConfiguration::mappingList($proposed['mappingConfig']);
                if ($mappings === null) {
                    return $this->errorResult(
                        'mappingConfig must be a list of mapping entries, one per column.'
                    );
                }
                $proposed['mappingConfig'] = $mappings;
            }

            $state = ProposedImportConfiguration::fold($stored, $proposed);

            // a select can only hold one of its options; a proposal must not hold more
            $unknownValues = ProposedImportConfiguration::unknownValues($state, $this->vocabulary->all());
            if ($unknownValues !== []) {
                return $this->errorResult(implode("\n", $unknownValues));
            }
            // identity and adapter type belong to the subject, never to a proposal
            $state['general']['name'] = $name;
            $state['general']['type'] = $existing->getType();
            // the subject strips these from its own state; proposing them back adds leaves
            // to the review that name a change nobody made
            foreach (ImportConfigSubjectHandler::VOLATILE_GENERAL as $volatile) {
                unset($state['general'][$volatile]);
            }

            $proposalId = bin2hex(random_bytes(16));
            $label = sprintf('Proposed changes to the %s configuration', $name);

            $this->sessionService->setProposalData($sessionId, $proposalId, [
                'proposalType' => self::PROPOSAL_TYPE,
                'subjectType' => ImportConfigSubjectHandler::TYPE,
                'subjectRef' => $name,
                'subjectState' => $state,
                'label' => $label,
            ], (int) $this->securityService->getCurrentUser()->getId());

            $result = ['proposalId' => $proposalId, 'name' => $name];
            $result += $this->widgetEmitter->buildAutoEmit(
                self::PROPOSAL_TYPE,
                [$proposalId],
                $summary ?? $label,
                null,
                $sessionId,
            );

            return $this->successResult($result);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(array|string $configuration): ?array
    {
        if (is_array($configuration)) {
            return $configuration;
        }

        $decoded = json_decode($configuration, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        try {
            $parsed = Yaml::parse($configuration);
        } catch (Throwable) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    private function load(string $name): ?Configuration
    {
        try {
            $configuration = Configuration::getByName($name);
        } catch (Throwable) {
            return null;
        }

        return is_string($configuration?->getName()) ? $configuration : null;
    }
}
