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
     * @param iterable<string, object> $loaders
     * @param iterable<string, object> $interpreters
     * @param iterable<string, object> $loadingStrategies
     * @param iterable<string, object> $locationStrategies
     * @param iterable<string, object> $publishingStrategies
     * @param iterable<string, object> $cleanupStrategies
     * @param iterable<string, object> $dataTargets
     * @param iterable<string, object> $operators
     */
    public function __construct(
        iterable $loaders,
        iterable $interpreters,
        iterable $loadingStrategies,
        iterable $locationStrategies,
        iterable $publishingStrategies,
        iterable $cleanupStrategies,
        iterable $dataTargets,
        iterable $operators,
    ) {
        $this->types = [
            ProposedImportConfiguration::FAMILY_LOADER => $this->keysOf($loaders),
            ProposedImportConfiguration::FAMILY_INTERPRETER => $this->keysOf($interpreters),
            ProposedImportConfiguration::FAMILY_LOADING => $this->keysOf($loadingStrategies),
            ProposedImportConfiguration::FAMILY_LOCATION => $this->keysOf($locationStrategies),
            ProposedImportConfiguration::FAMILY_PUBLISHING => $this->keysOf($publishingStrategies),
            ProposedImportConfiguration::FAMILY_CLEANUP => $this->keysOf($cleanupStrategies),
            ProposedImportConfiguration::FAMILY_DATA_TARGET => $this->keysOf($dataTargets),
            ProposedImportConfiguration::FAMILY_OPERATOR => $this->keysOf($operators),
        ];
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
