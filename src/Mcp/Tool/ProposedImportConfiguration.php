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

use function array_diff;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function range;

/**
 * What an agent hands propose_import_config, turned into the document a proposal can carry.
 *
 * Agents paraphrase a document rather than copy it: a section is left out, the mapping list
 * comes back wrapped or keyed by index, a key is invented. Each of those has one right reading,
 * and this is where it is decided — before anything reaches the change set.
 *
 * @internal
 */
final class ProposedImportConfiguration
{
    /** what an import configuration is made of; anything else means the document was invented */
    public const array SECTIONS = [
        'general', 'loaderConfig', 'interpreterConfig', 'resolverConfig',
        'processingConfig', 'mappingConfig', 'executionConfig', 'permissions',
    ];

    /** the key a wrapped mapping list is handed back under */
    private const string MAPPING_WRAPPER = 'mappings';

    /**
     * Top-level keys that are neither a section nor something the stored document already
     * carries — an installation may keep more than the editor shows, and that must not be
     * refused back.
     *
     * @param array<string, mixed> $proposed
     * @param array<string, mixed> $stored
     *
     * @return list<string>
     */
    public static function unknownSections(array $proposed, array $stored): array
    {
        return array_values(array_diff(array_keys($proposed), self::SECTIONS, array_keys($stored)));
    }

    /**
     * The proposal folded over the stored document, so a section the agent did not mention
     * keeps its value. Lists are replaced whole: merging them index by index would graft the
     * stored row's keys onto a row the agent rewrote, and leave a longer stored list's tail
     * behind a shorter proposed one.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $proposed
     *
     * @return array<string, mixed>
     */
    public static function fold(array $stored, array $proposed): array
    {
        foreach ($proposed as $key => $value) {
            $current = $stored[$key] ?? null;
            $stored[$key] = is_array($value) && is_array($current) && !array_is_list($value) && !array_is_list($current)
                ? self::fold($current, $value)
                : $value;
        }

        return $stored;
    }

    /**
     * The mapping list as a list. Agents hand it back wrapped in a key of its own or keyed by
     * index; both mean the same thing, and either would otherwise fold into the stored list as
     * a mixed array that no longer round-trips through the editor.
     *
     * @return list<mixed>|null null when it is not a mapping list at all
     */
    public static function mappingList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return $value;
        }

        if (array_keys($value) === [self::MAPPING_WRAPPER]) {
            return self::mappingList($value[self::MAPPING_WRAPPER]);
        }

        // keyed by index: the same list, spelt as an object
        return array_keys($value) === array_map(strval(...), range(0, count($value) - 1))
            ? array_values($value)
            : null;
    }
}
