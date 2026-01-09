<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConditionalConversion extends AbstractOperator implements SchemaAwareInterface
{
    /**
     * @var string
     */
    protected $original;

    /**
     * @var string
     */
    protected $converted;

    public function setSettings(array $settings): void
    {
        $this->original = $settings['original'] ?? '';
        $this->converted = $settings['converted'] ?? '';
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $origArr = explode('|', $this->original);
        $convArr = explode('|', $this->converted);
        foreach ($inputData as &$data) {
            $index = array_search($data, $origArr);
            if ($index !== false) {
                $data = $convArr[$index];
            } else {
                $index = array_search('*', $origArr);
                if ($index !== false) {
                    $data = $convArr[$index];
                }
            }
        }

        if ($returnScalar) {
            if (!empty($inputData)) {
                return reset($inputData);
            }

            return null;
        } else {
            return $inputData;
        }
    }

    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ])) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for simple test operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return $inputType;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts input values based on conditional mapping. '
            . 'Maps original values to converted values using pipe-separated lists. '
            . 'Supports wildcard (*) for default conversions.';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('original')
                    ->info(
                        'Pipe-separated list of original values to match (e.g., "yes|true|1"). '
                        . 'Use "*" as wildcard for any unmatched value.'
                    )
                    ->defaultValue('')
                ->end()
                ->scalarNode('converted')
                    ->info('Pipe-separated list of converted values corresponding to original values (e.g., "1|1|1")')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
