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
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Proposal\Mcp\ConfigProposalTools;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;

/**
 * The import configurations this installation has, so an agent can name one before reading it.
 *
 * @internal
 */
final readonly class ListImportConfigsTool
{
    private const string TOOL_NAME = 'list_import_configs';

    public function __construct(
        private ConfigProposalTools $tools,
        private ImportConfigPolicy $policy,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'List Import Configurations',
        description: 'List the Data Importer configurations, with their group and whether they are '
            . 'active. Use this to find the exact name to pass to get_import_config.',
        annotations: new ToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        )
    )]
    public function execute(): CallToolResult
    {
        return $this->tools->list($this->policy, self::TOOL_NAME);
    }
}
