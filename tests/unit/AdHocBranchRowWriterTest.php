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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\ObjectChanges;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\ObjectGoneException;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Contracts\ChangeControl\Exception\InvalidChangeException;
use Pimcore\Contracts\ChangeControl\FieldValue;

class AdHocBranchRowWriterTest extends Unit
{
    private const string OUTDOOR = '/Products/Outdoor';

    private AdHocFakeBranches $branches;

    private RunStoreInterface $runs;

    protected function _before(): void
    {
        $this->branches = new AdHocFakeBranches();
        $this->runs = AdHocRunFixtures::memoryStore();
    }

    private function writer(?AdHocTestObject $stored = null): BranchRowWriter
    {
        $objects = $this->createStub(ConcreteObjectResolverInterface::class);
        $objects->method('getById')->willReturn($stored);

        return new BranchRowWriter(
            $this->branches,
            new ObjectChanges(),
            $objects,
            $this->runs,
            AdHocRunFixtures::runMessages()
        );
    }

    private function branchRun(): RunRecord
    {
        return AdHocRunFixtures::run(['branch' => AdHocFakeBranches::BRANCH, 'targetFolder' => self::OUTDOOR]);
    }

    private function write(AdHocTestObject $object, bool $isNew, ?RunRecord $run = null): RowWrite
    {
        return new RowWrite(
            $object,
            ['sku' => 'SKU-1', 'name' => 'Desk'],
            $isNew,
            AdHocRunFixtures::user(),
            5,
            'SKU-1',
            self::OUTDOOR,
            ['SKU-1', 'Desk'],
            $run ?? $this->branchRun()
        );
    }

    /**
     * @return list<array{string, mixed}>
     */
    private function values(): array
    {
        return array_map(
            static fn (FieldValue $value): array => [$value->field, $value->value],
            $this->branches->recorded[0]->values()
        );
    }

    public function testARunOpensItsOwnBranchNamedAfterItsFileAndFolder(): void
    {
        $run = $this->writer()->open(
            AdHocRunFixtures::run(['targetFolder' => self::OUTDOOR]),
            AdHocRunFixtures::user()
        );

        $this->assertSame(AdHocFakeBranches::BRANCH, $run->branch);
        $this->assertTrue($run->throughBranch());
        $this->assertSame([
            'open "data-importer.ad-hoc-import.merge.branch-name'
            . ' {"{{file}}":"autumn.csv","{{folder}}":"/Products/Outdoor"}"'
            . ' in data-importer:ad-hoc-import:file-1 as run-user',
        ], $this->branches->calls);
    }

    public function testARowOfAnExistingObjectRecordsWhatItChangesAndIsRemembered(): void
    {
        $this->writer(new AdHocTestObject(['name' => 'Chair', 'price' => 10], 42))
            ->write($this->write(new AdHocTestObject(['name' => 'Desk', 'price' => 10], 42), false));

        $this->assertSame(['record 1 in data-importer:ad-hoc-import:file-1'], $this->branches->calls, 'one row a call');
        $this->assertSame(42, $this->branches->recorded[0]->id);
        $this->assertSame([['name', 'Desk']], $this->values(), 'the price did not change');
        $this->assertEquals(
            [new RecordedRow(
                5,
                42,
                null,
                'SKU-1',
                'update',
                'SKU-1',
                self::OUTDOOR,
                ['SKU-1', 'Desk'],
                ['sku' => 'SKU-1', 'name' => 'Desk']
            )],
            $this->runs->recorded($this->branchRun())
        );
    }

    public function testARowOfANewObjectCreatesItUnderTheRowsToken(): void
    {
        $this->writer()->write($this->write(new AdHocTestObject(['name' => 'Desk'], null, 3, 'SKU-9'), true));

        $create = $this->branches->recorded[0]->create;
        $this->assertSame(
            ['row-5', 'Product', 3, 'SKU-9'],
            [$create?->token, $create?->className, $create?->parent, $create?->key]
        );
        $this->assertSame([['name', 'Desk']], $this->values());
        $recorded = $this->runs->recorded($this->branchRun());
        $this->assertSame(
            [null, 'row-5', 'create', 'SKU-9'],
            [$recorded[0]->objectId, $recorded[0]->token, $recorded[0]->action, $recorded[0]->key]
        );
    }

    public function testARowTheBranchRefusesFailsAloneAndIsNotRemembered(): void
    {
        $this->branches->recordRefusal = new InvalidChangeException('no field "nme"');

        try {
            $this->writer(new AdHocTestObject([], 42))->write(
                $this->write(new AdHocTestObject(['name' => 'Desk'], 42), false)
            );
            $this->fail('the row fails');
        } catch (InvalidChangeException) {
            $this->assertSame([], $this->runs->recorded($this->branchRun()));
        }
    }

    public function testARowWhoseObjectWasDeletedMeanwhileFails(): void
    {
        $this->expectException(ObjectGoneException::class);

        $this->writer(null)->write($this->write(new AdHocTestObject(['name' => 'Desk'], 42), false));
    }

    public function testARowOfARunWithoutABranchIsNotWritten(): void
    {
        $this->expectException(\LogicException::class);

        $this->writer()->write($this->write(new AdHocTestObject(['name' => 'Desk']), true, AdHocRunFixtures::run()));
    }

    public function testTheEndOfTheRowsFindsOutNothingYet(): void
    {
        $this->assertSame([], $this->writer()->close($this->branchRun()), 'the merge tells what cannot be saved');
    }
}
