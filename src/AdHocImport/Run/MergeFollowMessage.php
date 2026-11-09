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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

/**
 * Asks a worker to look how the merge of a run goes.
 *
 * @internal
 */
final readonly class MergeFollowMessage
{
    public function __construct(
        public int $userId,
        public string $fileId,
    ) {
    }
}
