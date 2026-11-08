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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;

/**
 * A field the import writes.
 *
 * @internal
 */
final readonly class ImportField
{
    /**
     * @param string|null $key the field as the Direct data target names it; null for another target, whose values the
     *                         preview cannot read
     * @param StoreKey|null $storeKey for a classification store, the key of it the field is
     */
    public function __construct(
        public ?string $key,
        public ?string $language,
        public string $label,
        public ?StoreKey $storeKey = null,
    ) {
    }
}
