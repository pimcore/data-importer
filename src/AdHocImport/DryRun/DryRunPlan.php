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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcher;
use Pimcore\Model\User;

/**
 * What a dry run checks: the file's rows, how they find their objects and what is written into them.
 *
 * @internal
 */
final readonly class DryRunPlan
{
    /**
     * @param iterable<int, array<int|string, mixed>> $rows the file's rows in their order, without the header row
     * @param int $firstRowNumber the number of the first of them in the file
     * @param list<FieldWriter> $writers
     * @param list<int> $shown the positions among $writers of the fields the preview shows
     */
    public function __construct(
        public iterable $rows,
        public int $firstRowNumber,
        public RowMatcher $matcher,
        public array $writers,
        public array $shown,
        public string $matchField,
        public string $targetFolder,
        public string $newObjectFolder,
        public User $user,
    ) {
    }
}
