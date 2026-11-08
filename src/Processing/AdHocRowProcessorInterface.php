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

namespace Pimcore\Bundle\DataImporterBundle\Processing;

/**
 * Takes a queue item of an ad hoc import, which has no Data Importer configuration to process it by.
 *
 * @internal
 */
interface AdHocRowProcessorInterface
{
    /**
     * @param string $queueName the queue item's config name
     * @param int $userId the user who started the run
     * @param string $data the queue item's data
     */
    public function processRow(string $queueName, int $userId, string $data): void;
}
