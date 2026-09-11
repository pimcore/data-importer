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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function array_is_list;
use function is_array;
use Pimcore\Bundle\ChangeControlBundle\Merge\NodeKind;
use Pimcore\Bundle\ChangeControlBundle\Merge\StateShapeInterface;

/**
 * How the merge walks an import configuration.
 *
 * The engine's structural default reads a keyed array holding `type` or `path` as an element
 * reference, atomic as a whole. An import configuration is full of those keys and none of
 * them is a reference: `general.type` is the adapter, `loaderConfig.type` the loader,
 * `general.path` bookkeeping. Read that way every section is one leaf, a reviewer cannot
 * withhold a single field, and the diff says "general changed" instead of which key did.
 *
 * Lists stay atomic — the mapping list above all: its rows have no stable address, and it
 * is reviewed and applied whole.
 *
 * @internal
 */
final readonly class ImportConfigShape implements StateShapeInterface
{
    public function kindOf(array $segments, mixed $value): NodeKind
    {
        return is_array($value) && $value !== [] && !array_is_list($value)
            ? NodeKind::Container
            : NodeKind::Atomic;
    }
}
