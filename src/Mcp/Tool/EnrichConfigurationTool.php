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

use function is_array;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class EnrichConfigurationTool
{
    use ConfigurationParserTrait;
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'enrich_import_config';

    public function __construct(
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportProcessingService $importProcessingService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Enrich Import Configuration',
        description: 'Report the result type each mapping item\'s transformation pipeline '
            . 'produces, without storing anything. This is a diagnostic, not a required step: '
            . 'validate_import_config computes the same types itself, and save_import_config '
            . 'writes them into the configuration for you. Use it to work out why a target field '
            . 'is reported as incompatible, or to pick a target that accepts what a pipeline '
            . 'produces. Returns [{index, label, transformationResultType}].',
        // Computes over the supplied configuration; nothing is stored.
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'A full Data Importer configuration, or a single mapping item, as JSON '
                . 'or YAML. A single item is recognised by having label, dataSourceIndex and '
                . 'dataTarget without general or mappingConfig.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format of the configuration. Omit to auto-detect.',
            enum: ['json', 'yaml'],
        )]
        ?string $format = null,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $configArray = $this->parseConfiguration($configuration, $format ?? '');

            $types = $this->isSingleMappingItem($configArray)
                ? [$this->describeItem(0, $configArray, 'temp')]
                : $this->describeFullConfiguration($configArray);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        return $this->successResult(['types' => $types]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isSingleMappingItem(array $config): bool
    {
        return isset($config['label'], $config['dataSourceIndex'], $config['dataTarget'])
            && !isset($config['general'])
            && !isset($config['mappingConfig']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{index: int|string, label: string, transformationResultType: string}>
     *
     * @throws InvalidMcpToolArgumentException
     */
    private function describeFullConfiguration(array $config): array
    {
        $mappingConfig = $config['mappingConfig'] ?? null;
        if (!is_array($mappingConfig)) {
            throw new InvalidMcpToolArgumentException(
                'Configuration must have a mappingConfig array.'
            );
        }

        // Both shapes occur: mappingConfig as a plain list of items, and the nested
        // mappingConfig.mappingItems the Studio form writes.
        $items = is_array($mappingConfig['mappingItems'] ?? null)
            ? $mappingConfig['mappingItems']
            : $mappingConfig;

        $name = $config['general']['name'] ?? 'temp';

        $types = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidMcpToolArgumentException(
                    sprintf('mappingConfig[%s] must be an object.', $index)
                );
            }

            $types[] = $this->describeItem($index, $item, (string) $name);
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $mappingItem
     *
     * @return array{index: int|string, label: string, transformationResultType: string}
     *
     * @throws InvalidMcpToolArgumentException
     */
    private function describeItem(int|string $index, array $mappingItem, string $configName): array
    {
        try {
            $mappingConfiguration = $this->mappingConfigurationFactory
                ->loadMappingConfigurationItem($configName, $mappingItem, false);
        } catch (InvalidConfigurationException $e) {
            throw new InvalidMcpToolArgumentException(
                sprintf('mappingConfig[%s]: %s', $index, $e->getMessage())
            );
        }

        return [
            'index' => $index,
            'label' => (string) ($mappingItem['label'] ?? ''),
            'transformationResultType' => $this->importProcessingService
                ->evaluateTransformationResultDataType($mappingConfiguration),
        ];
    }
}
