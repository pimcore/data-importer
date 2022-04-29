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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator;

use Pimcore\Model\DataObject\Data\GeoCoordinates;

abstract class GeopolyAbstractOperator extends AbstractOperator
{
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
