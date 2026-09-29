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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\WritesElementsInterface;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;

/**
 * Applies a Data Importer mapping to an element without saving it, e.g. to preview the mapping or to record the
 * result elsewhere. Unlike an import it uses no stored configuration, no queue, no resolver and no events.
 */
final class MappingApplier
{
    // operators log under this configuration name; nothing is logged while a mapping is applied
    private const CONFIG_NAME = '';

    public function __construct(
        private readonly MappingConfigurationFactory $mappingConfigurationFactory,
        private readonly ImportProcessingService $importProcessingService,
        private readonly MappingApplicationScope $scope,
    ) {
    }

    /**
     * Lists why the mapping cannot be applied without saving: invalid items and operators or data targets that write
     * elements. An empty list means prepare() accepts the mapping.
     *
     * @param array<int|string, mixed> $mappingConfig the `mappingConfig` of a Data Importer configuration
     *
     * @return list<MappingIssue>
     */
    public function lint(array $mappingConfig): array
    {
        return $this->build($mappingConfig)['issues'];
    }

    /**
     * @param array<int|string, mixed> $mappingConfig the `mappingConfig` of a Data Importer configuration
     *
     * @throws InvalidConfigurationException if lint() reports an issue
     */
    public function prepare(array $mappingConfig): PreparedMapping
    {
        ['items' => $items, 'issues' => $issues] = $this->build($mappingConfig);
        if ($issues !== []) {
            throw new InvalidConfigurationException(implode("\n", array_map('strval', $issues)));
        }

        return new PreparedMapping($items, $this->importProcessingService, $this->scope);
    }

    /**
     * @param array<int|string, mixed> $mappingConfig
     *
     * @return array{items: array<int|string, MappingConfiguration>, issues: list<MappingIssue>}
     */
    private function build(array $mappingConfig): array
    {
        $items = [];
        $issues = [];

        foreach ($mappingConfig as $index => $entry) {
            if (!is_array($entry)) {
                $issues[] = new MappingIssue($index, '', 'Mapping item is not an array.');

                continue;
            }

            $label = (string) ($entry['label'] ?? '');

            try {
                $item = $this->mappingConfigurationFactory->loadMappingConfigurationItem(self::CONFIG_NAME, $entry);
            } catch (\Throwable $exception) {
                // settings of data targets fail with \TypeError on malformed input
                $issues[] = new MappingIssue($index, $label, $exception->getMessage());

                continue;
            }

            $operatorConfigs = array_values($entry['transformationPipeline'] ?? []);
            foreach ($item->getTransformationPipeline() as $position => $operator) {
                if ($operator instanceof WritesElementsInterface) {
                    $issues[] = new MappingIssue($index, $label, sprintf(
                        'Operator `%s` writes elements and cannot be applied without saving.',
                        $operatorConfigs[$position]['type'] ?? $operator::class
                    ));
                }
            }

            if ($item->getDataTarget() instanceof WritesElementsInterface) {
                $issues[] = new MappingIssue($index, $label, sprintf(
                    'Data target `%s` writes elements and cannot be applied without saving.',
                    $entry['dataTarget']['type'] ?? $item->getDataTarget()::class
                ));
            }

            $items[$index] = $item;
        }

        return ['items' => $items, 'issues' => $issues];
    }
}
