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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ConvertedColumn;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ColumnWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ConfigurationItemWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ImportField;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\UserReferenceVisibility;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowOutcome;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\PreparedMapping;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\DataTargetInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ObjectField;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AdHocRowImporterReferenceTest extends Unit
{
    private function importer(): RowImporter
    {
        return new RowImporter(
            $this->createStub(RowWriterInterface::class),
            new MappingApplicationScope(),
            AdHocRunFixtures::dryRunMessages(),
            AdHocRunFixtures::runMessages(),
            new EventDispatcher(),
            new NullLogger()
        );
    }

    private function existing(): RowMatch
    {
        $object = $this->createStub(Concrete::class);
        $object->method('getId')->willReturn(11);
        $object->method('getKey')->willReturn('SKU-1');
        $object->method('getRealPath')->willReturn('/Products/Archive/');
        $object->method('isAllowed')->willReturn(true);

        return new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $object);
    }

    public function testAReferenceIsCheckedForTheUserOfTheRowNotTheOneThePlanWasMadeFor(): void
    {
        $planned = AdHocRunFixtures::user();
        $current = AdHocRunFixtures::user(false);

        $reference = $this->createStub(Concrete::class);
        $reference->method('getFullPath')->willReturn('/Products/Hidden');
        $reference->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $user): bool => $type === 'view' && $user === $planned
        );
        $holder = new class($reference) extends Concrete {
            public function __construct(private ElementInterface $reference)
            {
            }

            public function getRelated(): ElementInterface
            {
                return $this->reference;
            }
        };

        $scope = new MappingApplicationScope();
        $operator = (new \ReflectionClass(ObjectField::class))->newInstanceWithoutConstructor();
        $operator->setSettings(['attribute' => 'related']);
        $operator->setMappingApplicationScope($scope);
        $target = new class() implements DataTargetInterface {
            /** @var list<mixed> */
            public array $assigned = [];

            public function setSettings(array $settings): void
            {
                // this target only records what the mapping assigns
            }

            public function assignData(ElementInterface $element, $data): void
            {
                $this->assigned[] = $data;
            }
        };
        $item = new MappingConfiguration();
        $item->setLabel('Related');
        $item->setDataSourceIndex('holder');
        $item->setTransformationPipeline([$operator]);
        $item->setDataTarget($target);
        $mapping = new PreparedMapping(
            [$item],
            (new \ReflectionClass(ImportProcessingService::class))->newInstanceWithoutConstructor(),
            $scope
        );
        $writer = new ConfigurationItemWriter(
            new ImportField('name', null, 'Name'),
            $mapping,
            ['holder'],
            new UserReferenceVisibility($planned)
        );
        $plan = AdHocRunFixtures::plan(
            [$this->existing(), $this->existing()],
            [$writer],
            $planned,
            [['sku' => 'SKU-1', 'holder' => $holder]]
        );

        $this->importer()->import($plan, $current, 0, []);
        $this->assertSame([null], $target->assigned, 'the plan\'s user may view it, the row\'s user may not');

        $target->assigned = [];
        $this->importer()->import($plan, $planned, 0, []);
        $this->assertSame(['/Products/Hidden'], $target->assigned);
    }

    public function testAColumnsLoadedElementIsCheckedForTheUserOfTheRowNotTheOneThePlanWasMadeFor(): void
    {
        $planned = AdHocRunFixtures::user();
        $current = AdHocRunFixtures::user(false);

        $reference = $this->createStub(Concrete::class);
        $reference->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $user): bool => $type === 'view' && $user === $planned
        );
        $object = new class() extends Concrete {
            /** @var list<mixed> */
            public array $assigned = [];

            public function getId(): ?int
            {
                return 11;
            }

            public function getKey(): ?string
            {
                return 'SKU-1';
            }

            public function getRealPath(): string
            {
                return '/Products/Archive/';
            }

            public function isAllowed(string $type, ?User $user = null): bool
            {
                return true;
            }

            // getPermissions() reaches the data object's dao through __call
            public function __call($method, $arguments): mixed
            {
                return null;
            }

            public function setRelated(mixed $related, ?string $language = null): static
            {
                $this->assigned[] = $related;

                return $this;
            }
        };
        $writer = new ColumnWriter(
            new ImportField('related', null, 'Related'),
            'holder',
            new ConvertedColumn([$reference], [false], [false], null)
        );
        $match = new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $object);
        $plan = AdHocRunFixtures::plan([$match, $match], [$writer], $planned, [['sku' => 'SKU-1', 'holder' => '7']]);

        $outcome = $this->importer()->import($plan, $current, 0, []);
        $this->assertTrue($outcome->isFailure(), 'the plan\'s user may view it, the row\'s user may not');
        $this->assertSame([], $object->assigned, 'the object\'s relation stays as it is');

        $outcome = $this->importer()->import($plan, $planned, 0, []);
        $this->assertSame(RowOutcome::UPDATED, $outcome->result);
        $this->assertSame([$reference], $object->assigned);
    }
}
