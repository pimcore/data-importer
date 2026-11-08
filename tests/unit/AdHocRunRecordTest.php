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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowOutcome;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;

class AdHocRunRecordTest extends Unit
{
    public function testARunCountsEveryRowByWhatBecameOfIt(): void
    {
        $run = AdHocRunFixtures::run(['total' => 3])
            ->counted(RowOutcome::saved(false, 'SKU-1'))
            ->counted(RowOutcome::saved(true, 'SKU-2'));

        $this->assertSame([1, 1, 0], [$run->updated, $run->created, $run->failed]);
        $this->assertSame(2, $run->processed());
        $this->assertFalse($run->isComplete());

        $run = $run->counted(RowOutcome::failed('SKU-3', null, 'no'));

        $this->assertSame(1, $run->failed);
        $this->assertTrue($run->isComplete(), 'a failed row counts, so a run always ends');
        $this->assertTrue($run->isRunning(), 'until it is finished');
    }

    public function testAFinishedRunIsDone(): void
    {
        $run = AdHocRunFixtures::run()->finished(1800000000);

        $this->assertFalse($run->isRunning());
        $this->assertSame(RunRecord::DONE, $run->state);
        $this->assertSame(1800000000, $run->finishedAt);
    }

    public function testARunSurvivesBeingStored(): void
    {
        $run = AdHocRunFixtures::run(['updated' => 4, 'configuration' => ['configuration' => [], 'mapping' => []]]);

        $this->assertEquals($run, RunRecord::fromArray($run->toArray()));
        $this->assertSame(FileType::Csv->value, $run->toArray()['type'], 'stored as a plain value');
    }

    public function testAQueueItemNamesItsFileWithinTheColumnsLength(): void
    {
        $fileId = '123e4567-e89b-12d3-a456-426614174000';
        $name = RunRecord::queueName($fileId);

        $this->assertLessThanOrEqual(80, strlen($name));
        $this->assertSame($fileId, RunRecord::fileIdOf($name));
    }
}
