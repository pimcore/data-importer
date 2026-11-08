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
 * Where the AI stage of a request stands, with its suggestions once it finished.
 *
 * @internal
 *
 * @phpstan-import-type Suggestion from ColumnMatcher
 */
final readonly class AiSuggestionResult
{
    /**
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        public AiSuggestionStatus $status,
        public array $suggestions = [],
    ) {
    }
}
