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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AdHocDirectRowWriterTest extends Unit
{
    public function testAnObjectIsSavedAsTheUserAndPostSaveFollowsWithoutAConfigurationName(): void
    {
        $calls = [];
        $object = $this->createMock(Concrete::class);
        $object->expects($this->once())->method('setUserModification')->with(AdHocRunFixtures::USER_ID)->willReturnSelf();
        $object->expects($this->never())->method('setUserOwner');
        $object->expects($this->once())->method('save')->willReturnCallback(static function () use (&$calls, $object) {
            $calls[] = 'save';

            return $object;
        });

        $events = new EventDispatcher();
        $seen = null;
        $events->addListener(PostSaveEvent::class, static function (PostSaveEvent $event) use (&$calls, &$seen): void {
            $calls[] = 'post';
            $seen = $event;
        });

        (new DirectRowWriter($events))->write(new RowWrite($object, ['sku' => 'SKU-1'], false, AdHocRunFixtures::user()));

        $this->assertSame(['save', 'post'], $calls);
        $this->assertSame('', $seen->getConfigName());
        $this->assertSame(['sku' => 'SKU-1'], $seen->getRawData());
        $this->assertSame($object, $seen->getDataObject());
    }

    public function testANewObjectBelongsToTheUser(): void
    {
        $object = $this->createMock(Concrete::class);
        $object->expects($this->once())->method('setUserOwner')->with(AdHocRunFixtures::USER_ID)->willReturnSelf();
        $object->method('setUserModification')->willReturnSelf();

        (new DirectRowWriter(new EventDispatcher()))
            ->write(new RowWrite($object, [], true, AdHocRunFixtures::user()));
    }

    public function testAFailedSaveSendsNoPostSave(): void
    {
        $object = $this->createStub(Concrete::class);
        $object->method('setUserModification')->willReturnSelf();
        $object->method('save')->willThrowException(new \RuntimeException('no'));
        $events = new EventDispatcher();
        $events->addListener(PostSaveEvent::class, function (): void {
            $this->fail('nothing was saved');
        });

        $this->expectException(\RuntimeException::class);
        (new DirectRowWriter($events))->write(new RowWrite($object, [], false, AdHocRunFixtures::user()));
    }

    public function testADirectRunHasNothingToOpenOrToFindOutAtTheEnd(): void
    {
        $writer = new DirectRowWriter(new EventDispatcher());

        $writer->open(AdHocRunFixtures::run());

        $this->assertSame([], $writer->close(AdHocRunFixtures::run()));
    }
}
