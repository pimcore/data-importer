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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

/**
 * @internal
 */
enum FileType: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public static function fromFileName(string $fileName): ?self
    {
        return static::tryFrom(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)));
    }
}
