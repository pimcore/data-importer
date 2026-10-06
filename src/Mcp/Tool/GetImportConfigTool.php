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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Proposal\Mcp\ConfigProposalTools;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;

/**
 * One import configuration as it is stored — the document propose_import_config expects back
 * with changes applied.
 *
 * @internal
 */
final readonly class GetImportConfigTool
{
    private const string TOOL_NAME = 'get_import_config';

    public function __construct(
        private ConfigProposalTools $tools,
        private ImportConfigPolicy $policy,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Configuration',
        description: 'Read one Data Importer configuration in full: general, loaderConfig, '
            . 'interpreterConfig, resolverConfig, processingConfig, mappingConfig and executionConfig. '
            . 'Always read a configuration before proposing changes to it — propose_import_config '
            . 'takes the complete document back, not a patch.',
        annotations: new ToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        )
    )]
    public function execute(
        #[Schema(type: 'string', description: 'Name of the configuration, as list_import_configs reports it.')]
        string $name,
    ): CallToolResult {
        return $this->tools->get($this->policy, self::TOOL_NAME, $name);
    }
}
