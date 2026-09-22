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
 * Proposes an import configuration — a change to one, or a new one — instead of writing it.
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

    /** the adapter type this bundle registers with the Data Hub */
    private const string CONFIG_TYPE = 'dataImporterDataObject';

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
        description: 'Propose a Data Importer configuration for user approval — changes to an existing '
            . 'one, or a new one under a name that does not exist yet. Does NOT write — the '
            . 'configuration rides a change set the user reviews. For a change, read the current '
            . 'configuration first with get_import_config and send the COMPLETE document back with '
            . 'your changes applied; anything you leave out keeps its current value. For a new one, '
            . 'send a complete document (copy a similar configuration and adjust it): it needs a '
            . 'loader, a file format, a target class and the resolver strategies; it starts inactive '
            . 'unless active is set. Returns {proposalId, name}. The review widget is shown '
            . 'automatically once the proposal succeeds; end your turn after a successful proposal '
            . 'and wait for the decision.',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: false,
        )
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Name of the configuration: an existing one to change, or a new one to create.'
        )]
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
            $state = $this->buildState($name, $configuration, $existing);

            $proposalId = bin2hex(random_bytes(16));
            $label = $existing === null
                ? sprintf('A new %s configuration', $name)
                : sprintf('Proposed changes to the %s configuration', $name);

            $this->sessionService->setProposalData($sessionId, $proposalId, [
                'proposalType' => self::PROPOSAL_TYPE,
                'subjectType' => ImportConfigSubjectHandler::TYPE,
                'subjectRef' => $name,
                'subjectState' => $state,
                'label' => $label,
                // the card and the review title say "new" rather than "changed" on this
                'isNew' => $existing === null,
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
        } catch (ProposalRefusedException $e) {
            return $this->errorResult($e->getMessage());
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

    /**
     * The document as it would be recorded, or a refusal saying what to send instead.
     *
     * @throws ProposalRefusedException
     */
    private function buildState(string $name, array|string $configuration, ?Configuration $existing): array
    {
        if ($existing === null && !ProposedImportConfiguration::isValidName($name)) {
            throw new ProposalRefusedException(sprintf(
                '"%s" cannot name a configuration: use letters, digits, "-" and "_", '
                . 'starting with a letter or digit.',
                $name,
            ));
        }

        // a name nothing is stored under creates: the document is the whole configuration
        $stored = $existing?->getConfiguration() ?? [];
        $state = ProposedImportConfiguration::fold($stored, $this->readSections($configuration, $stored));

        $this->assertRecordable($state, $stored, $existing);

        return $this->withSubjectIdentity($state, $name, $existing);
    }

    /**
     * @throws ProposalRefusedException
     */
    private function readSections(array|string $configuration, array $stored): array
    {
        $proposed = $this->decode($configuration);
        if ($proposed === null) {
            throw new ProposalRefusedException(
                'The configuration must be an object, or a JSON or YAML object string.'
            );
        }

        $unknown = ProposedImportConfiguration::unknownSections($proposed, $stored);
        if ($unknown !== []) {
            throw new ProposalRefusedException(sprintf(
                '%s is not part of an import configuration. Read the current document with '
                . 'get_import_config and send that back with your changes applied, rather than '
                . 'writing one from memory.',
                implode(', ', $unknown),
            ));
        }

        // a field the editor binds nothing to reviews as a change and applies as nothing
        $invented = ProposedImportConfiguration::unknownPaths($proposed, $stored);
        if ($invented !== []) {
            throw new ProposalRefusedException(sprintf(
                '%s is not a field of an import configuration. Read the current document with '
                . 'get_import_config and change the keys it already has, rather than spelling '
                . 'one from memory.',
                implode(', ', $invented),
            ));
        }

        if (array_key_exists('mappingConfig', $proposed)) {
            $mappings = ProposedImportConfiguration::mappingList($proposed['mappingConfig']);
            if ($mappings === null) {
                throw new ProposalRefusedException(
                    'mappingConfig must be a list of mapping entries, one per column.'
                );
            }
            $proposed['mappingConfig'] = $mappings;
        }

        return $proposed;
    }

    /**
     * @throws ProposalRefusedException
     */
    private function assertRecordable(array $state, array $stored, ?Configuration $existing): void
    {
        // a select can only hold one of its options; a proposal must not hold more
        $unknownValues = ProposedImportConfiguration::unknownValues($state, $this->vocabulary->all());
        if ($unknownValues !== []) {
            throw new ProposalRefusedException(implode("\n", $unknownValues));
        }

        // a change the import would ignore is not worth a reviewer's turn; a create is
        // reviewed whole, and a copy legitimately carries the inert values its source has
        if ($existing === null) {
            return;
        }

        $ineffective = ProposedImportConfiguration::ineffectiveChanges($stored, $state);
        if ($ineffective !== []) {
            throw new ProposalRefusedException(implode("\n", $ineffective));
        }
    }

    /**
     * @throws ProposalRefusedException
     */
    private function withSubjectIdentity(array $state, string $name, ?Configuration $existing): array
    {
        // identity and adapter type belong to the subject, never to a proposal
        $state['general']['name'] = $name;
        $state['general']['type'] = $existing?->getType() ?? self::CONFIG_TYPE;

        if ($existing === null) {
            $missing = ProposedImportConfiguration::missingForCreate($state);
            if ($missing !== []) {
                throw new ProposalRefusedException(sprintf(
                    'A new configuration needs %s. Read a similar one with get_import_config and send '
                    . 'the complete document under the new name.',
                    implode(', ', $missing),
                ));
            }
            // a pipeline nobody switched on must not start running because it was reviewed
            $state['general']['active'] ??= false;
            $state['general']['path'] ??= '';
        }

        // the subject strips these from its own state; proposing them back adds leaves
        // to the review that name a change nobody made
        foreach (ImportConfigSubjectHandler::VOLATILE_GENERAL as $volatile) {
            unset($state['general'][$volatile]);
        }

        return $state;
    }
}
