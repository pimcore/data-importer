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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue as MultiValueDefinition;

/**
 * The slot of a classification store a mapping entry writes to: the resolved group and key, the
 * token the entry named them with - which is what a message about them has to quote - the variant
 * the entry addressed, and what the key declares about the qualifiers of its values.
 *
 * @internal
 */
final class ClassificationstoreKeySlot
{
    public function __construct(
        public readonly string $token,
        public readonly int $groupId,
        public readonly int $keyId,
        public readonly ?string $variant,
        public readonly ?MultiValueDefinition $declaration
    ) {
    }
}
