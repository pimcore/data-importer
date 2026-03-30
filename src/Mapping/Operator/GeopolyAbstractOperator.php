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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator;

use Pimcore\Model\DataObject\Data\GeoCoordinates;

/**
 * @internal
 */
abstract class GeopolyAbstractOperator extends AbstractOperator
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array
     */
    public function process($inputData, bool $dryRun = false)
    {
        $data = [];
        $i = 0;
        if (is_array($inputData)) {
            foreach ($inputData as $input) {
                if (is_array($input)) {
                    $data[] = new GeoCoordinates($input[0], $input[1]);
                } else {
                    $coordinates[] = $input;
                    if (++$i % 2 === 0) {
                        $data[] = new GeoCoordinates($coordinates[0], $coordinates[1]);
                        $coordinates = null;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param mixed $inputData
     *
     * @return array
     */
    public function generateResultPreview($inputData)
    {
        $preview = null;
        if (is_array($inputData)) {
            foreach ($inputData as $key => $item) {
                if ($item instanceof GeoCoordinates) {
                    $preview[$key] = 'Lat.: ' . $item->getLatitude() . ' Long.: ' . $item->getLongitude();
                }
            }
        }

        return $preview;
    }
}
