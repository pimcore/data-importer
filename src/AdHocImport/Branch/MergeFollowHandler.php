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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\MergeFollowMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class MergeFollowHandler
{
    public function __construct(
        private BranchMergeInterface $merges,
    ) {
    }

    public function __invoke(MergeFollowMessage $message): void
    {
        $this->merges->follow($message->userId, $message->fileId);
    }
}
