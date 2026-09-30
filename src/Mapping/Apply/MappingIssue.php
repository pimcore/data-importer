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
 * A message about a mapping item: why it cannot be applied without saving (MappingApplier::lint()), or what an
 * operator would have logged during an import (PreparedMapping::apply()).
 */
final readonly class MappingIssue
{
    public function __construct(
        public int|string $itemIndex,
        public string $itemLabel,
        public string $message,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('Mapping item %s (`%s`): %s', $this->itemIndex, $this->itemLabel, $this->message);
    }
}
