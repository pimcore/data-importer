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
 * What the user chose; a null is left to the file (the detected delimiter, the first sheet) or the default.
 *
 * @internal
 */
final readonly class SettingsInput
{
    public function __construct(
        public ?string $delimiter = null,
        public ?string $enclosure = null,
        public ?string $escape = null,
        public bool $hasHeaderRow = true,
        public ?string $sheetName = null,
    ) {
    }
}
