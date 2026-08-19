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
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
class QuantityValue extends AbstractOperator implements SchemaAwareInterface, TransformationTypeAwareInterface
{
    /**
     * @var string
     */
    protected $unitSource = 'id';

    /**
     * @var string
     */
    protected $staticUnitId;

    /**
     * @var bool
     */
    protected $unitNullIfNoValue;

    public function setSettings(array $settings): void
    {
        $this->unitSource = $settings['unitSourceSelect'] ?? 'id';
        $this->staticUnitId = $settings['staticUnitSelect'] ?? null;
        $this->unitNullIfNoValue = (bool) ($settings['unitNullIfNoValueCheckbox'] ?? false);
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return \Pimcore\Model\DataObject\Data\AbstractQuantityValue|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        $value = null;
        $unitId = null;

        switch ($this->unitSource) {
            case 'id':
                if (is_array($inputData)) {
                    if (isset($inputData[1])) {
                        $unit = Unit::getById($inputData[1]);
                        if ($unit instanceof Unit) {
                            $unitId = $unit->getId();
                        }
                    }
                    $value = $inputData[0] ?? null;
                }

                break;

            case 'abbr':
                if (is_array($inputData)) {
                    if (isset($inputData[1])) {
                        $unit = Unit::getByAbbreviation($inputData[1]);
                        if ($unit instanceof Unit) {
                            $unitId = $unit->getId();
                        }
                    }
                    $value = $inputData[0] ?? null;
                }

                break;

            case 'static':
                $value = $inputData;
                if (is_array($inputData)) {
                    $value = $inputData[0] ?? null;
                }
                $unitId = $this->staticUnitId;
        }

        $value = $value ?? null;
        if (($value === null || $value === '') && $this->unitNullIfNoValue) {
            $unitId = null;
        }
        if (($value === null || $value === '') && $unitId === null) {
            return null;
        }

        return new \Pimcore\Model\DataObject\Data\QuantityValue(
            $value === null ? null : floatval($value),
            $unitId ?? null
        );
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
        if ($this->unitSource !== 'static') {
            if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
                throw new InvalidConfigurationException(sprintf(
                    "Unsupported input type '%s' for quantity value operator at transformation position %s",
                    $inputType,
                    $index
                ));
            }
        } elseif (
            $inputType !== TransformationDataTypeService::DEFAULT_TYPE &&
            $inputType !== TransformationDataTypeService::NUMERIC
        ) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for quantity value operator with static unit at " .
                'transformation position %s',
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::QUANTITY_VALUE;
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof \Pimcore\Model\DataObject\Data\QuantityValue) {
            return 'QuantityValue: ' . $inputData->getValue() . ' ' .
                ($inputData->getUnit() ? $inputData->getUnit()->getAbbreviation() : '');
        }

        return $inputData;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts input data into a QuantityValue object with a numeric value and unit. '
            . 'Supports unit by ID, abbreviation, or static unit selection.';
    }

    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_ARRAY,
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::NUMERIC
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::QUANTITY_VALUE
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
                ->enumNode('unitSourceSelect')
                    ->info(
                        'How to determine the unit: "id" (unit ID from input), '
                        . '"abbr" (abbreviation from input), or "static" (fixed unit)'
                    )
                    ->values(['id', 'abbr', 'static'])
                    ->defaultValue('id')
                ->end()
                ->scalarNode('staticUnitSelect')
                    ->info('Unit ID to use when unitSourceSelect is "static"')
                    ->defaultValue(null)
                ->end()
                ->booleanNode('unitNullIfNoValueCheckbox')
                    ->info('If true, sets unit to null when value is null or empty')
                    ->defaultValue(false)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
