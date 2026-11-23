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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\DataObject\Data\QuantityValueRange as QuantityValueRangeModel;

/**
 * The two bounds of a quantity value range and the unit the two of them are given in.
 *
 * @internal
 */
final class QuantityValueRange extends AbstractOperator
{
    use QuantityValueUnitSourceTrait;
    use RangeBoundsTrait;

    private const OPERATOR_LABEL = 'quantity value range';

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     */
    public function process($inputData, bool $dryRun = false): ?QuantityValueRangeModel
    {
        [$minimum, $maximum] = $this->extractBounds($inputData);
        $unitId = $this->resolveUnitId($inputData);

        $withoutBounds = $minimum === null && $maximum === null;
        if ($this->unitNullIfNoValue && $withoutBounds) {
            $unitId = null;
        }

        if ($withoutBounds && $unitId === null) {
            return null;
        }

        return new QuantityValueRangeModel($minimum, $maximum, $unitId === null ? null : (string) $unitId);
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof QuantityValueRangeModel) {
            return 'QuantityValueRange: '
                . $this->renderBounds($inputData->getMinimum(), $inputData->getMaximum())
                . ' ' . ($inputData->getUnit()?->getAbbreviation() ?? '');
        }

        return $inputData;
    }

    /**
     * Unlike a plain quantity value, a range needs two sources for its bounds whatever the unit
     * comes from, so a static unit does not make a single column enough.
     *
     * @param string $inputType
     * @param int|null $index
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        $this->assertBoundsInput($inputType, self::OPERATOR_LABEL, $index);

        return TransformationDataTypeService::QUANTITY_VALUE_RANGE;
    }

    protected function getOperatorLabel(): string
    {
        return self::OPERATOR_LABEL;
    }

    /**
     * The unit follows the two bounds, the way the unit of a plain quantity value follows its one
     * value - so the shared unit resolution is handed the row from the upper bound on.
     */
    private function resolveUnitId(mixed $inputData): mixed
    {
        $values = is_array($inputData) ? array_values($inputData) : [$inputData];
        [, $unitId] = $this->extractValueAndUnitId([$values[1] ?? null, $values[2] ?? null]);

        return $unitId;
    }
}
