<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\DataObject\Data\RgbaColor;

class AsColor extends AbstractOperator
{
    /**
     * @throws \Exception
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            if (count($inputData) > 0 && is_numeric($inputData[0])) {
                return new RgbaColor(...$inputData);
            }
        } elseif (str_starts_with($inputData, '#')) {
            $color = new RgbaColor();
            $color->setHex($inputData);

            return $color;
        }

        return new RgbaColor();
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof RgbaColor) {
            return $inputData->__toString();
        }

        return $inputData;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        return TransformationDataTypeService::RGBA_COLOR;
    }
}
