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

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * One answer at a time to a run waiting for a decision: two would both find it waiting, and the later one would end
 * a run whose merge the other has started.
 *
 * @internal
 */
final class DecisionLock
{
    // taking many objects out of a branch takes minutes; a request that died gives the decision free again after this
    private const float SECONDS = 3600.0;

    public static function of(LockFactory $locks, int $userId, string $fileId): LockInterface
    {
        return $locks->createLock(sprintf('data_importer_ad_hoc_decision_%d_%s', $userId, $fileId), self::SECONDS);
    }
}
