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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

/**
 * What a `loadDataObject` or `loadAsset` operator is about to look up, handed to a ReferenceLookupInterface.
 *
 * `key` is the value the operator would look up with its own strategy: trimmed for the `id` and `path` strategies,
 * unchanged for `attribute` (without the wildcards a partial match adds). The attribute settings are only set for the
 * `attribute` strategy of `loadDataObject`.
 */
final readonly class ReferenceQuery
{
    public function __construct(
        public ReferenceType $type,
        public ReferenceLoadStrategy $loadStrategy,
        public string $key,
        public ?string $classId = null,
        public ?string $attributeName = null,
        public ?string $attributeLanguage = null,
        public bool $partialMatch = false,
        public bool $includeUnpublished = false,
    ) {
    }
}
