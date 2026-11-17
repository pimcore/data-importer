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
use Pimcore\Model\DataObject\Data\NumericRange;

/**
 * The two bounds of a numeric range, read from two sources of the row.
 *
 * @internal
 */
final class AsNumericRange extends AbstractOperator
{
    use RangeBoundsTrait;

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     */
    public function process($inputData, bool $dryRun = false): ?NumericRange
    {
        [$minimum, $maximum] = $this->extractBounds($inputData);
        if ($minimum === null && $maximum === null) {
            return null;
        }

        return new NumericRange($minimum, $maximum);
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof NumericRange) {
            return $this->renderBounds($inputData->getMinimum(), $inputData->getMaximum());
        }

        return $inputData;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        $this->assertBoundsInput($inputType, 'numeric range', $index);

        return TransformationDataTypeService::NUMERIC_RANGE;
    }
}
