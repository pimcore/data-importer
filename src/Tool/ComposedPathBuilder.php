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

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Model\Element\Service as ElementService;

class ComposedPathBuilder
{
    public static function buildPath(array $inputData, string $path, string $type = 'object'): string
    {
        $parts = explode('/', $path);

        foreach ($parts as $partIndex => $part) {
            $matches = [];

            preg_match_all('/\$((\[[0-9A-Za-z_-]+\])+)/', $part, $matches);

            if (count($matches) && count($matches[0])) {
                foreach ($matches[0] as $mIndex => $m) {
                    //get keys out of array string
                    $keyString = $matches[1][$mIndex];
                    $keys = explode(',', str_replace(']', '', str_replace('[', '', str_replace('][', ',', $keyString))));
                    $val = $inputData;
                    foreach ($keys as $k) {
                        $val = $val[$k];
                    }
                    $parts[$partIndex] = str_replace($m, $val, $parts[$partIndex]);
                }
            }

            $parts[$partIndex] = ElementService::getValidKey($parts[$partIndex], $type);
        }

        return implode('/', $parts);
    }
}
