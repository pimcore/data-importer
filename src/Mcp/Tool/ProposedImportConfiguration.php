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
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationName;
use function range;
use function sprintf;

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

    /**
     * What the Data Hub adds to every stored document around the sections: a copy of an
     * existing configuration carries them, and a create must not be refused for it.
     */
    private const array ENVELOPE = ['workspaces', 'schema'];

    /** the key a wrapped mapping list is handed back under */
    private const string MAPPING_WRAPPER = 'mappings';

    public const string FAMILY_LOADER = 'loader';

    public const string FAMILY_INTERPRETER = 'interpreter';

    public const string FAMILY_LOADING = 'loading strategy';

    public const string FAMILY_LOCATION = 'location strategy';

    public const string FAMILY_PUBLISHING = 'publishing strategy';

    public const string FAMILY_CLEANUP = 'cleanup strategy';

    public const string FAMILY_DATA_TARGET = 'data target';

    public const string FAMILY_OPERATOR = 'transformation';

    /** what a configuration cannot run without; the editor marks the same fields required */
    private const array REQUIRED_TO_CREATE = [
        'loaderConfig.type',
        'interpreterConfig.type',
        'resolverConfig.dataObjectClassId',
        'resolverConfig.loadingStrategy.type',
        'resolverConfig.createLocationStrategy.type',
        'resolverConfig.locationUpdateStrategy.type',
        'resolverConfig.publishingStrategy.type',
    ];

    /**
     * Settings the editor only shows — and the import only reads — while another one is on.
     * setting => the switch it depends on
     */
    private const array GATED = [
        'processingConfig.cleanup.strategy' => 'processingConfig.cleanup.doCleanup',
        'processingConfig.cleanup.doCleanup' => 'processingConfig.idDataIndex',
        'processingConfig.doDeltaCheck' => 'processingConfig.idDataIndex',
        'processingConfig.doDeltaCheckCheck' => 'processingConfig.idDataIndex',
    ];

    /** where a document names a type, and which family it must come from */
    private const array TYPED = [
        'loaderConfig.type' => self::FAMILY_LOADER,
        'interpreterConfig.type' => self::FAMILY_INTERPRETER,
        'resolverConfig.loadingStrategy.type' => self::FAMILY_LOADING,
        'resolverConfig.createLocationStrategy.type' => self::FAMILY_LOCATION,
        'resolverConfig.locationUpdateStrategy.type' => self::FAMILY_LOCATION,
        'resolverConfig.publishingStrategy.type' => self::FAMILY_PUBLISHING,
        'processingConfig.cleanup.strategy' => self::FAMILY_CLEANUP,
    ];

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
        return array_values(array_diff(array_keys($proposed), self::SECTIONS, self::ENVELOPE, array_keys($stored)));
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

    /**
     * Every type the document names that this installation does not have, each with what it
     * could have been. A select in the editor can only hold one of its options; a proposal must
     * not be able to hold more.
     *
     * @param array<string, mixed>        $state
     * @param array<string, list<string>> $vocabulary family => accepted types
     *
     * @return list<string>
     */
    public static function unknownValues(array $state, array $vocabulary): array
    {
        $problems = [];
        $check = static function (string $path, mixed $value, string $family) use (&$problems, $vocabulary): void {
            if ($value === null || $value === '' || !isset($vocabulary[$family])) {
                return;
            }
            if (!is_string($value) || !in_array($value, $vocabulary[$family], true)) {
                $problems[] = sprintf(
                    '%s: "%s" is not a %s here; use one of %s',
                    $path,
                    is_string($value) ? $value : 'non-string',
                    $family,
                    implode(', ', $vocabulary[$family]),
                );
            }
        };

        foreach (self::TYPED as $path => $family) {
            $check($path, self::at($state, $path), $family);
        }

        $mappings = $state['mappingConfig'] ?? [];
        if (!is_array($mappings)) {
            return $problems;
        }
        foreach ($mappings as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $check(sprintf('mappingConfig[%s].dataTarget.type', $index), $row['dataTarget']['type'] ?? null, self::FAMILY_DATA_TARGET);
            foreach (is_array($row['transformationPipeline'] ?? null) ? $row['transformationPipeline'] : [] as $step => $operator) {
                $check(
                    sprintf('mappingConfig[%s].transformationPipeline[%s].type', $index, $step),
                    is_array($operator) ? ($operator['type'] ?? null) : null,
                    self::FAMILY_OPERATOR,
                );
            }
        }

        return $problems;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function at(array $state, string $path): mixed
    {
        $node = $state;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !isset($node[$segment])) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    public static function isValidName(string $name): bool
    {
        return ConfigurationName::isValid($name);
    }

    /**
     * The fields a new configuration still lacks. An update inherits them from the stored
     * document; a create has nothing to inherit from, and a configuration without a loader,
     * a format or a target class is not one the importer can run.
     *
     * @param array<string, mixed> $state
     *
     * @return list<string>
     */
    public static function missingForCreate(array $state): array
    {
        $missing = [];
        foreach (self::REQUIRED_TO_CREATE as $path) {
            $value = self::at($state, $path);
            if ($value === null || $value === '') {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * The settings a proposal changes that cannot take effect, because the switch they sit
     * behind is off in the same document. The reviewer would see a change to a field the
     * editor does not even show, and the import would ignore it.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $state
     *
     * @return list<string>
     */
    public static function ineffectiveChanges(array $stored, array $state): array
    {
        $problems = [];
        foreach (self::GATED as $path => $switch) {
            $before = self::at($stored, $path);
            $after = self::at($state, $path);
            if ($before === $after || $after === null || $after === '' || $after === false) {
                continue;
            }
            $gate = self::at($state, $switch);
            if ($gate === null || $gate === '' || $gate === false) {
                $problems[] = sprintf(
                    '%s has no effect while %s is off; switch it on in the same proposal, or leave %s as it is',
                    $path,
                    $switch,
                    $path,
                );
            }
        }

        return $problems;
    }
}
