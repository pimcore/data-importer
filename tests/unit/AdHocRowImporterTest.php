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
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowOutcome;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\ProcessElementExceptionEvent;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\Element\DuplicateFullPathException;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AdHocRowImporterTest extends Unit
{
    /** @var list<RowWrite> */
    private array $written = [];

    private ?\Throwable $failWith = null;

    private EventDispatcher $events;

    protected function _before(): void
    {
        $this->written = [];
        $this->failWith = null;
        $this->events = new EventDispatcher();
    }

    private function importer(): RowImporter
    {
        $writer = new class($this) implements RowWriterInterface {
            public function __construct(private AdHocRowImporterTest $test)
            {
            }

            public function open(RunRecord $run): void
            {
                // these tests look only at what is written
            }

            public function write(RowWrite $write): void
            {
                $this->test->recordWrite($write);
            }

            public function close(RunRecord $run): array
            {
                return [];
            }
        };

        return new RowImporter(
            $writer,
            new MappingApplicationScope(),
            AdHocRunFixtures::dryRunMessages(),
            AdHocRunFixtures::runMessages(),
            $this->events,
            new NullLogger()
        );
    }

    public function recordWrite(RowWrite $write): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        $this->written[] = $write;
    }

    /**
     * @param array<string, bool> $rights
     * @param list<string>|null $languages the languages the user may edit; null is all of them
     */
    private function object(array $rights = [], bool $published = false, ?array $languages = null): Concrete
    {
        $object = $this->createStub(Concrete::class);
        $object->method('getId')->willReturn(11);
        $object->method('getKey')->willReturn('SKU-1');
        $object->method('getRealPath')->willReturn('/Products/Archive/');
        $object->method('isPublished')->willReturn($published);
        $object->method('isAllowed')->willReturnCallback(
            static fn (string $type): bool => $rights[$type] ?? true
        );
        // getPermissions() reaches the data object's dao through __call
        $object->method('__call')->willReturnCallback(
            static fn (string $name): ?array => $name === 'getPermissions' && $languages !== null
                ? ['lEdit' => implode(',', $languages)]
                : null
        );

        return $object;
    }

    /**
     * @param list<string>|null $languages the languages the user may edit; null is all of them
     */
    private function newObject(bool $mayCreate, ?array $languages = null): Concrete
    {
        $parent = $this->createStub(Folder::class);
        $parent->method('getRealFullPath')->willReturn('/Products/New');
        $parent->method('isAllowed')->willReturnCallback(
            static fn (string $type): bool => $type === 'create' && $mayCreate
        );

        $object = $this->createStub(Concrete::class);
        $object->method('getId')->willReturn(null);
        $object->method('getKey')->willReturn('SKU-NEW');
        // as on a real object not saved yet, whose path is null
        $object->method('getRealPath')->willThrowException(new \TypeError('getRealPath(): null returned'));
        $object->method('getParent')->willReturn($parent);
        $object->method('__call')->willReturnCallback(
            static fn (string $name): ?array => $name === 'getPermissions' && $languages !== null
                ? ['lEdit' => implode(',', $languages)]
                : null
        );

        return $object;
    }

    private function plan(RowMatch $match, array $writers = [], ?User $user = null): ImportPlan
    {
        return AdHocRunFixtures::plan([$match], $writers, $user, [['sku' => 'SKU-1', 'name' => 'Chair']]);
    }

    private function existing(Concrete $object): RowMatch
    {
        return new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $object);
    }

    public function testARowIsMappedThenHandedToTheWriterAsTheUser(): void
    {
        $user = AdHocRunFixtures::user();
        $object = $this->object();
        $writer = AdHocRunFixtures::writer();

        $outcome = $this->importer()->import($this->plan($this->existing($object), [$writer], $user), $user, 0, []);

        $this->assertSame(RowOutcome::UPDATED, $outcome->result);
        $this->assertSame('SKU-1', $outcome->matchValue);
        $this->assertSame([0], $writer->writes);
        $this->assertCount(1, $this->written);
        $this->assertSame($object, $this->written[0]->object);
        $this->assertFalse($this->written[0]->isNew);
        $this->assertSame($user, $this->written[0]->user);
        $this->assertSame(['sku' => 'SKU-1', 'name' => 'Chair'], $this->written[0]->row);
    }

    public function testANewObjectNeedsCreateOnItsFolderAndIsReportedAsCreated(): void
    {
        $user = AdHocRunFixtures::user();
        $allowed = $this->newObject(true);

        $outcome = $this->importer()->import(
            $this->plan(new RowMatch(RowMatchOutcome::New, 'SKU-NEW', $allowed), [], $user),
            $user,
            0,
            []
        );

        $this->assertSame(RowOutcome::CREATED, $outcome->result);
        $this->assertSame('create', $outcome->action);
        $this->assertTrue($this->written[0]->isNew);

        $this->written = [];
        $refused = $this->importer()->import(
            $this->plan(new RowMatch(RowMatchOutcome::New, 'SKU-NEW', $this->newObject(false)), [], $user),
            $user,
            0,
            []
        );

        $this->assertTrue($refused->isFailure());
        $this->assertSame(
            'data-importer.ad-hoc-import.run.may-not-create {"{{folder}}":"/Products/New"}',
            $refused->message
        );
        $this->assertSame([], $this->written, 'nothing is written');
    }

    public function testANewObjectCannotWriteALanguageTheUserMayNotEdit(): void
    {
        $user = AdHocRunFixtures::user(false);
        $writers = [AdHocRunFixtures::writer(language: 'de')];
        $match = new RowMatch(RowMatchOutcome::New, 'SKU-NEW', $this->newObject(true, ['en']));

        $refused = $this->importer()->import($this->plan($match, $writers, $user), $user, 0, []);

        $this->assertSame(
            'data-importer.ad-hoc-import.run.may-not-edit-language'
            . ' {"{{language}}":"de","{{folder}}":"/Products/New"}',
            $refused->message
        );
        $this->assertSame([], $writers[0]->writes, 'the value is not mapped');
        $this->assertSame([], $this->written, 'nothing is written');

        $leftOut = $this->importer()->import($this->plan($match, $writers, $user), $user, 0, [0]);

        $this->assertSame(RowOutcome::CREATED, $leftOut->result, 'without the value the row can be saved');
        $this->assertSame([], $writers[0]->writes);
    }

    public function testAFailedWriteOfANewObjectIsReportedForItsRow(): void
    {
        $user = AdHocRunFixtures::user();
        $this->failWith = new DuplicateFullPathException('path');

        $outcome = $this->importer()->import(
            $this->plan(new RowMatch(RowMatchOutcome::New, 'SKU-NEW', $this->newObject(true)), [], $user),
            $user,
            0,
            []
        );

        $this->assertSame('create', $outcome->action);
        $this->assertSame(
            'data-importer.ad-hoc-import.check.key-taken {"{{key}}":"SKU-NEW","{{folder}}":"/Products/New"}',
            $outcome->message
        );
    }

    public function testATargetThePreviewCannotReadIsStillRefusedTheLanguageItWrites(): void
    {
        $user = AdHocRunFixtures::user(false);
        $writers = [AdHocRunFixtures::writer(language: 'de', key: null)];

        $outcome = $this->importer()->import(
            $this->plan($this->existing($this->object(languages: ['en'])), $writers, $user),
            $user,
            0,
            []
        );

        $this->assertStringContainsString('may-not-edit-language', (string) $outcome->message);
        $this->assertSame([], $writers[0]->writes);
        $this->assertSame([], $this->written);
    }

    public function testARowFindingNoObjectIsReportedWithTheChecksReason(): void
    {
        $user = AdHocRunFixtures::user();
        $match = new RowMatch(RowMatchOutcome::DuplicateInFile, 'SKU-1', firstRow: 2);

        $outcome = $this->importer()->import($this->plan($match, [], $user), $user, 0, []);

        $this->assertTrue($outcome->isFailure());
        $this->assertNull($outcome->action);
        $this->assertStringContainsString('duplicate-in-file', (string) $outcome->message);
        $this->assertStringContainsString('"{{row}}":"2"', (string) $outcome->message);
    }

    public function testAnObjectMayNotBeSavedOrSeenWithoutTheRight(): void
    {
        $user = AdHocRunFixtures::user();

        $unpublished = $this->importer()->import(
            $this->plan($this->existing($this->object(['save' => false])), [], $user),
            $user,
            0,
            []
        );
        $this->assertSame(
            'data-importer.ad-hoc-import.run.may-not-save {"{{folder}}":"/Products/Archive"}',
            $unpublished->message
        );

        $hidden = $this->importer()->import(
            $this->plan($this->existing($this->object(['view' => false])), [], $user),
            $user,
            0,
            []
        );
        $this->assertStringContainsString('not-viewable', (string) $hidden->message);
        $this->assertStringNotContainsString('/Products/Archive', (string) $hidden->message, 'nor where it lies');

        $this->assertSame([], $this->written);
    }

    public function testChangingAPublishedObjectNeedsPublishNotJustSave(): void
    {
        $user = AdHocRunFixtures::user();

        $refused = $this->importer()->import(
            $this->plan($this->existing($this->object(['publish' => false], true)), [], $user),
            $user,
            0,
            []
        );
        $this->assertTrue($refused->isFailure(), 'save alone would only make a version of it');

        $allowed = $this->importer()->import(
            $this->plan($this->existing($this->object(['save' => false], true)), [], $user),
            $user,
            0,
            []
        );
        $this->assertFalse($allowed->isFailure());
    }

    public function testALanguageTheUserMayNotEditRefusesTheRowUnlessItsValueIsLeftOut(): void
    {
        $user = AdHocRunFixtures::user(false);
        $writers = [AdHocRunFixtures::writer(language: 'de')];
        $object = $this->object(languages: ['en']);

        $refused = $this->importer()->import($this->plan($this->existing($object), $writers, $user), $user, 0, []);

        $this->assertSame(
            'data-importer.ad-hoc-import.run.may-not-edit-language '
            . '{"{{language}}":"de","{{folder}}":"/Products/Archive"}',
            $refused->message
        );

        $leftOut = $this->importer()->import($this->plan($this->existing($object), $writers, $user), $user, 0, [0]);

        $this->assertFalse($leftOut->isFailure(), 'the value it may not write is not written');
        $this->assertSame([], $writers[0]->writes);
    }

    public function testAnAdministratorEditsEveryLanguage(): void
    {
        $user = AdHocRunFixtures::user();
        $writers = [AdHocRunFixtures::writer(language: 'de')];

        $outcome = $this->importer()->import(
            $this->plan($this->existing($this->object(languages: ['en'])), $writers, $user),
            $user,
            0,
            []
        );

        $this->assertFalse($outcome->isFailure());
    }

    public function testAValueLeftOutByThePreviewIsNotWritten(): void
    {
        $user = AdHocRunFixtures::user();
        $writers = [AdHocRunFixtures::writer(), AdHocRunFixtures::writer()];

        $this->importer()->import($this->plan($this->existing($this->object()), $writers, $user), $user, 0, [1]);

        $this->assertSame([0], $writers[0]->writes);
        $this->assertSame([], $writers[1]->writes);
    }

    public function testAValueTheFieldRefusesFailsTheRowBeforeAnythingIsWritten(): void
    {
        $user = AdHocRunFixtures::user();
        $events = 0;
        $this->events->addListener(PreSaveEvent::class, static function () use (&$events): void {
            $events++;
        });

        $outcome = $this->importer()->import(
            $this->plan($this->existing($this->object()), [AdHocRunFixtures::writer(ValueError::Invalid)], $user),
            $user,
            0,
            []
        );

        $this->assertTrue($outcome->isFailure());
        $this->assertStringContainsString('value.invalid', (string) $outcome->message);
        $this->assertSame([], $this->written);
        $this->assertSame(0, $events, 'listeners do not see a row that will not be saved');
    }

    public function testPreSaveRunsWithoutAConfigurationNameAfterTheMappingAndCanDropTheRow(): void
    {
        $user = AdHocRunFixtures::user();
        $writer = AdHocRunFixtures::writer();
        $seen = [];
        $this->events->addListener(
            PreSaveEvent::class,
            static function (PreSaveEvent $event) use (&$seen, $writer): void {
                $seen = [$event->getConfigName(), $event->getRawData(), $writer->writes];
                $event->setSkipSave(true);
            }
        );

        $outcome = $this->importer()->import(
            $this->plan($this->existing($this->object()), [$writer], $user),
            $user,
            0,
            []
        );

        $this->assertSame(['', ['sku' => 'SKU-1', 'name' => 'Chair'], [0]], $seen);
        $this->assertTrue($outcome->isFailure());
        $this->assertSame('data-importer.ad-hoc-import.run.left-out-by-listener', $outcome->message);
        $this->assertSame([], $this->written);
    }

    public function testPreSaveIsNotSentForARowTheUserHasNoRightsOn(): void
    {
        $user = AdHocRunFixtures::user();
        $events = 0;
        $this->events->addListener(PreSaveEvent::class, static function () use (&$events): void {
            $events++;
        });

        $this->importer()->import(
            $this->plan($this->existing($this->object(['save' => false])), [], $user),
            $user,
            0,
            []
        );

        $this->assertSame(0, $events);
    }

    public function testAFailedWriteIsReportedForItsRowAndGoesToTheExceptionEvent(): void
    {
        $user = AdHocRunFixtures::user();
        $exception = null;
        $this->events->addListener(
            ProcessElementExceptionEvent::class,
            static function (ProcessElementExceptionEvent $event) use (&$exception): void {
                $exception = $event;
            }
        );
        $this->failWith = new \RuntimeException('SQLSTATE: secret value of another object');

        $outcome = $this->importer()->import($this->plan($this->existing($this->object()), [], $user), $user, 0, []);

        $this->assertSame('data-importer.ad-hoc-import.run.save-failed-unknown', $outcome->message);
        $this->assertStringNotContainsString('secret', (string) $outcome->message, 'no database text reaches the user');
        $this->assertInstanceOf(ProcessElementExceptionEvent::class, $exception);
        $this->assertSame('', $exception->getConfigName());
        $this->assertSame($this->failWith, $exception->getException());
    }

    public function testWriteFailuresAreToldApartByWhatKeptTheRowFromBeingSaved(): void
    {
        $user = AdHocRunFixtures::user();
        $cases = [
            [
                new DuplicateFullPathException('path'),
                'check.key-taken {"{{key}}":"SKU-1","{{folder}}":"/Products/Archive"}',
            ],
            [new ValidationException('name is too long'), 'run.save-failed {"{{reason}}":"name is too long"}'],
            [
                new ValidationException(
                    'unique constraint violation',
                    0,
                    new UniqueConstraintViolationException($this->createStub(DriverException::class), null)
                ),
                'run.must-be-unique',
            ],
        ];

        foreach ($cases as [$failure, $expected]) {
            $this->failWith = $failure;
            $outcome = $this->importer()->import(
                $this->plan($this->existing($this->object()), [], $user),
                $user,
                0,
                []
            );

            $this->assertSame('data-importer.ad-hoc-import.' . $expected, $outcome->message);
        }
    }
}
