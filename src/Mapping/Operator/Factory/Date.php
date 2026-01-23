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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Date extends AbstractOperator implements SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    /**
     * @var string
     */
    protected $format;

    public function setSettings(array $settings): void
    {
        $this->format = $settings['format'] ?? 'Y-m-d';
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach ($inputData as &$data) {
            if (!empty($data)) {
                $data = \Carbon\Carbon::createFromFormat($this->format, $data);
            } else {
                $data = null;
            }
        }

        if ($returnScalar) {
            return reset($inputData);
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
                "Unsupported input type '%s' for date operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        if ($inputType === TransformationDataTypeService::DEFAULT_ARRAY) {
            return TransformationDataTypeService::DATE_ARRAY;
        }

        return TransformationDataTypeService::DATE;
    }

    /**
     * @param mixed $inputData
     *
     * @return array|mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof \DateTime) {
            return $inputData->format('c');
        }

        if (is_array($inputData)) {
            $preview = [];

            foreach ($inputData as $key => $data) {
                if ($data instanceof \DateTime) {
                    $preview[$key] = $data->format('c');
                } else {
                    $preview[$key] = $data;
                }
            }

            return $preview;
        }

        return $inputData;
    }

    public function getSchemaDescription(): string
    {
        return 'Parses date strings into DateTime objects using a specified format. '
            . 'Supports both single values and arrays.';
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
        return [
            TransformationDataTypeService::DATE,
            TransformationDataTypeService::DATE_ARRAY
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('format')
                    ->info('PHP date format string for parsing input dates (e.g., Y-m-d, d/m/Y H:i:s)')
                    ->defaultValue('Y-m-d')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
