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
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class StringReplace extends AbstractOperator implements SchemaAwareInterface, TransformationTypeAwareInterface
{
    private string $search;

    private string $replace;

    public function setSettings(array $settings): void
    {
        $this->search = $settings['search'] ?? '';
        $this->replace = $settings['replace'] ?? '';
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

        foreach ($inputData as &$data) {
            $data = str_replace($this->search, $this->replace, $data);
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

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ])) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for string replace operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return $inputType;
    }

    public function getSchemaDescription(): string
    {
        return 'Replaces all occurrences of a search string with a replacement string.';
    }

    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return $this->getAcceptedInputTypes();
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('search')
                    ->info('The string to search for')
                    ->defaultValue('')
                ->end()
                ->scalarNode('replace')
                    ->info('The replacement string')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
