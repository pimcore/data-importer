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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\ChangeControlBundle\ChangeSet\MappedParameter\ListChangeSetsParameters;
use Pimcore\Bundle\ChangeControlBundle\ChangeSet\Schema\ChangeSetItem;
use Pimcore\Bundle\ChangeControlBundle\ChangeSet\Service\ChangeSetServiceInterface;
use Pimcore\Bundle\ChangeControlBundle\Contract\ChangeControlAuthoringInterface;
use Pimcore\Bundle\ChangeControlBundle\Contract\ChangeSetSummary;
use Pimcore\Bundle\ChangeControlBundle\Storage\Schema;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectRef;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ChangeSetPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Symfony\Component\Uid\Uuid;

class ChangeSetPreviewScopeResolverTest extends Unit
{
    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    protected function _before(): void
    {
        // Change Control is an optional integration this repository cannot install
        if (!interface_exists(ChangeSetServiceInterface::class)) {
            static::markTestSkipped('Change Control bundle not installed');
        }
    }

    public function testAListedChangeSetYieldsWhatItProposesForTheConfiguration(): void
    {
        $subject = new SubjectRef(ImportConfigPolicy::SUBJECT_TYPE, Uuid::v4(), 'car-import');
        $changeSets = $this->createMock(ChangeSetServiceInterface::class);
        $changeSets->expects($this->once())
            ->method('list')
            ->with($this->callback(static fn (ListChangeSetsParameters $p): bool => $p->getSubjectType()
                    === ImportConfigPolicy::SUBJECT_TYPE
                && $p->getSubjectRef() === 'car-import'
                && $p->getContextRef() === 'agent-session:7'))
            ->willReturn([$this->item(self::CHANGE_SET)]);
        $authoring = $this->createStub(ChangeControlAuthoringInterface::class);
        $authoring->method('subjectsOf')->willReturn([
            new SubjectRef('data-hub-config', Uuid::v4(), 'car-import'),
            $subject,
        ]);
        $authoring->method('subjectStatesOf')->willReturn([
            $subject->key() => [
                'subject' => $subject,
                'proposed' => ['interpreterConfig' => ['type' => 'csv']],
                'base' => [],
            ],
        ]);

        static::assertSame(
            ['interpreterConfig' => ['type' => 'csv']],
            (new ChangeSetPreviewScopeResolver($changeSets, $authoring))
                ->proposal('car-import', new PreviewScope(self::CHANGE_SET, 'agent-session:7'))
        );
    }

    /** the listing answers only with change sets this user may read in that context */
    public function testAChangeSetTheListingDoesNotShowBindsNothing(): void
    {
        $changeSets = $this->createStub(ChangeSetServiceInterface::class);
        $changeSets->method('list')->willReturn([$this->item('0198f2c1-4b7a-7c3d-9e5f-1a2b3c4d5e6f')]);
        $authoring = $this->createMock(ChangeControlAuthoringInterface::class);
        $authoring->expects($this->never())->method('subjectStatesOf');

        static::assertNull(
            (new ChangeSetPreviewScopeResolver($changeSets, $authoring))
                ->proposal('car-import', new PreviewScope(self::CHANGE_SET))
        );
    }

    public function testAScopeThatIsNoChangeSetIdBindsNothing(): void
    {
        $changeSets = $this->createMock(ChangeSetServiceInterface::class);
        $changeSets->expects($this->never())->method('list');

        static::assertNull(
            (new ChangeSetPreviewScopeResolver($changeSets, $this->createStub(ChangeControlAuthoringInterface::class)))
                ->proposal('car-import', new PreviewScope('car-import'))
        );
    }

    public function testTheListingIsReadPastAFullPage(): void
    {
        $other = $this->item('0198f2c1-4b7a-7c3d-9e5f-1a2b3c4d5e6f');
        $full = array_fill(0, ListChangeSetsParameters::MAX_PAGE_SIZE, $other);
        $changeSets = $this->createMock(ChangeSetServiceInterface::class);
        $changeSets->expects($this->exactly(2))
            ->method('list')
            ->willReturnCallback(
                fn (ListChangeSetsParameters $p): array => $p->getPage() === 1 ? $full : [$this->item(self::CHANGE_SET)]
            );
        $authoring = $this->createStub(ChangeControlAuthoringInterface::class);
        $authoring->method('subjectsOf')->willReturn([]);

        // listed, but the change set holds no import configuration of that name
        static::assertNull(
            (new ChangeSetPreviewScopeResolver($changeSets, $authoring))
                ->proposal('car-import', new PreviewScope(self::CHANGE_SET))
        );
    }

    public function testAnOpenChangeSetIsAskedInItsContext(): void
    {
        $authoring = $this->createMock(ChangeControlAuthoringInterface::class);
        $authoring->expects($this->once())
            ->method('summaries')
            ->with([Uuid::fromString(self::CHANGE_SET)], 'agent-session:7')
            ->willReturn([self::CHANGE_SET => $this->summary(Schema::STATE_DRAFT)]);

        static::assertTrue(
            (new ChangeSetPreviewScopeResolver($this->createStub(ChangeSetServiceInterface::class), $authoring))
                ->isOpen(new PreviewScope(self::CHANGE_SET, 'agent-session:7'))
        );
    }

    public function testADecidedOrMissingChangeSetIsNotOpen(): void
    {
        $authoring = $this->createStub(ChangeControlAuthoringInterface::class);
        $authoring->method('summaries')->willReturnOnConsecutiveCalls(
            [self::CHANGE_SET => $this->summary(Schema::STATE_DISCARDED)],
            [],
        );
        $resolver = new ChangeSetPreviewScopeResolver($this->createStub(ChangeSetServiceInterface::class), $authoring);

        static::assertFalse($resolver->isOpen(new PreviewScope(self::CHANGE_SET)));
        static::assertFalse($resolver->isOpen(new PreviewScope(self::CHANGE_SET)));
        static::assertFalse($resolver->isOpen(new PreviewScope('car-import')));
    }

    private function summary(int $state): ChangeSetSummary
    {
        return new ChangeSetSummary(
            Uuid::fromString(self::CHANGE_SET),
            ImportConfigPolicy::SUBJECT_TYPE,
            'car-import',
            'label',
            $state,
            0,
            null,
            'agent',
            null,
        );
    }

    private function item(string $id): ChangeSetItem
    {
        return new ChangeSetItem(
            $id,
            ImportConfigPolicy::SUBJECT_TYPE,
            'car-import',
            [],
            'label',
            'open',
            0,
            [],
            'agent'
        );
    }
}
