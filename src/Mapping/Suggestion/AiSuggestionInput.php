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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion;

/**
 * What one request hands to the agent.
 *
 * @internal
 *
 * @phpstan-import-type Column from ColumnMatcher
 * @phpstan-import-type Target from ColumnMatcher
 */
final readonly class AiSuggestionInput
{
    /**
     * @param list<Column> $columns the open columns
     * @param list<Target> $targets the fields the columns may fill
     * @param list<string> $languages the valid languages
     * @param array<string, list<string>> $samples values per column; empty when none may be sent
     * @param list<array{field: string, language: string|null}> $taken what the name comparison already suggested
     */
    public function __construct(
        public string $classId,
        public array $columns,
        public array $targets,
        public array $languages,
        public array $samples = [],
        public array $taken = [],
    ) {
    }
}
