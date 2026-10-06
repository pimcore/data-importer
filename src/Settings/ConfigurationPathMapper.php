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

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use function array_is_list;
use function array_key_exists;
use function array_shift;
use function array_slice;
use function array_unshift;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * Translates between the two addressings of one configuration: the STORED document, and the
 * form the Studio editor binds to. `transformBackendToForm()` is not shape-preserving, so a
 * dotted address into the stored document does not name the same field in the form.
 *
 * Two rules cover every divergence; everything else is the same path on both sides.
 *
 * @internal
 */
final readonly class ConfigurationPathMapper
{
    /** the general.* keys the form lifts to the top level */
    private const array FLATTENED = ['active', 'description', 'group', 'name'];

    private const string GENERAL = 'general';

    private const string MAPPING = 'mappingConfig';

    /**
     * Stored address to form address, or null when the form has no field for it - an
     * address under a mapping item this configuration does not carry, most of all.
     *
     * @param array<string, mixed> $configuration the stored document the address is read against
     */
    public function toFormPath(string $storedPath, array $configuration): ?string
    {
        $segments = $this->segments($storedPath);
        if ($segments === []) {
            return null;
        }

        if ($segments[0] === self::GENERAL) {
            return $this->liftGeneral($segments);
        }

        if ($segments[0] === self::MAPPING && count($segments) > 1) {
            $index = $this->indexOfMappingId($segments[1], $configuration);

            return $index === null ? null : $this->replaceSecond($segments, (string) $index);
        }

        return $storedPath;
    }

    /**
     * Form address to stored address, or null when nothing in the stored document answers
     * to it - a mapping row the form holds at an index the document does not have.
     *
     * @param array<string, mixed> $configuration
     */
    public function toStoredPath(string $formPath, array $configuration): ?string
    {
        $segments = $this->segments($formPath);
        if ($segments === []) {
            return null;
        }

        if (in_array($segments[0], self::FLATTENED, true)) {
            array_unshift($segments, self::GENERAL);

            return implode('.', $segments);
        }

        if ($segments[0] === self::MAPPING && count($segments) > 1) {
            $id = $this->mappingIdAtIndex($segments[1], $configuration);

            return $id === null ? null : $this->replaceSecond($segments, $id);
        }

        return $formPath;
    }

    /**
     * The mapping items keyed by their `mappingId`, in document order. An item without one -
     * a configuration never saved through Studio, which is where the id is minted - is
     * skipped rather than given a positional key, so a caller can tell the difference.
     *
     * @param array<string, mixed> $configuration
     *
     * @return array<string, int> mappingId => index in the stored list
     */
    public function mappingIndex(array $configuration): array
    {
        $items = $configuration[self::MAPPING] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            return [];
        }

        $index = [];
        foreach ($items as $position => $item) {
            $id = is_array($item) ? ($item['mappingId'] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $index[$id] = $position;
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function indexOfMappingId(string $id, array $configuration): ?int
    {
        $index = $this->mappingIndex($configuration);

        return array_key_exists($id, $index) ? $index[$id] : null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function mappingIdAtIndex(string $position, array $configuration): ?string
    {
        if (!ctype_digit($position)) {
            return null;
        }

        foreach ($this->mappingIndex($configuration) as $id => $at) {
            if ($at === (int) $position) {
                return $id;
            }
        }

        return null;
    }

    /**
     * `general.name` is the exception: the form binds the configuration's name, which the
     * detail view supplies separately, so the address still lands on a real field.
     *
     * @param list<string> $segments
     */
    private function liftGeneral(array $segments): ?string
    {
        $lifted = array_slice($segments, 1);
        if ($lifted === [] || !in_array($lifted[0], self::FLATTENED, true)) {
            return null;
        }

        return implode('.', $lifted);
    }

    /**
     * @param list<string> $segments
     */
    private function replaceSecond(array $segments, string $replacement): string
    {
        $head = array_shift($segments);
        array_shift($segments);
        array_unshift($segments, $head, $replacement);

        return implode('.', $segments);
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return $path === '' ? [] : explode('.', $path);
    }
}
