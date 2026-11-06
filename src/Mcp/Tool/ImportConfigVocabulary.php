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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool;

use function array_keys;
use function array_map;
use function iterator_to_array;

/**
 * The adapter and strategy types this installation actually has, per family, read off the
 * same service tags the factories are built from — so a proposal can only name what a save
 * could load.
 *
 * @internal
 */
final class ImportConfigVocabulary
{
    /** @var array<string, list<string>> */
    private array $types;

    /**
     * One tagged iterator per family, keyed by the family name the refusals print. Keyed rather
     * than one argument each: the families are data, and the list grows with the tag families.
     *
     * @param array<string, iterable<string, object>> $families
     */
    public function __construct(array $families)
    {
        $this->types = array_map($this->keysOf(...), $families);
    }

    /**
     * @return array<string, list<string>> family => the types it accepts
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * @param iterable<string, object> $services
     *
     * @return list<string>
     */
    private function keysOf(iterable $services): array
    {
        return array_keys(iterator_to_array($services, true));
    }
}
