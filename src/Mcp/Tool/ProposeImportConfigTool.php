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

use function bin2hex;
use Closure;
use function is_string;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalWidgetEmitter;
use Pimcore\Bundle\PimcoreAgentBundle\Security\BoundSessionReferenceResolver;
use Pimcore\Bundle\PimcoreAgentBundle\Service\AgentSessionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function random_bytes;
use function sprintf;
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

    public function __construct(
        private AgentSessionServiceInterface $sessionService,
        private ProposalWidgetEmitter $widgetEmitter,
        private BoundSessionReferenceResolver $boundSessionReferenceResolver,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
        private ImportConfigProposalBuilder $proposals,
        /** the configuration stored under a name, null when there is none */
        private ?Closure $find = null,
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
                . 'interpreterConfig, resolverConfig, processingConfig, mappingConfig, '
                . 'executionConfig and permissions sections, with your changes applied. '
                . 'permissions holds {"user": [...], "role": [...]}, each entry '
                . '{"name": "...", "read": bool, "update": bool, "delete": bool}; an entry is '
                . 'matched by name, so take names from list_config_principals and never invent '
                . 'one. Leaving permissions empty means everyone with the Data Importer '
                . 'permission may use the configuration.'
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
            $state = $this->proposals->build($name, $configuration, $existing);

            $proposalId = bin2hex(random_bytes(16));
            $label = $existing === null
                ? sprintf('A new %s configuration', $name)
                : sprintf('Proposed changes to the %s configuration', $name);

            $this->sessionService->setProposalData($sessionId, $proposalId, [
                'proposalType' => self::PROPOSAL_TYPE,
                'subjectType' => ImportConfigPolicy::SUBJECT_TYPE,
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

    private function load(string $name): ?Configuration
    {
        // only a name nothing is stored under creates: a failed read is an error, never "new"
        $configuration = ($this->find ?? Configuration::getByName(...))($name);

        return is_string($configuration?->getName()) ? $configuration : null;
    }
}
