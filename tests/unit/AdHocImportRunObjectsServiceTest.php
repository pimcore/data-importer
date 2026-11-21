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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunObjectsService;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdHocImportRunObjectsServiceTest extends Unit
{
    private const array FIELDS = [
        ['key' => 'name', 'language' => null, 'storeKey' => null, 'label' => 'Name'],
        ['key' => 'price', 'language' => null, 'storeKey' => null, 'label' => 'Price'],
    ];

    private RunStoreInterface $runs;

    /** @var array<int, Concrete> */
    private array $objects = [];

    protected function _before(): void
    {
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->objects = [
            11 => self::object(11, 'NH-1001', ['name' => 'Trail jacket', 'price' => 189.9]),
            12 => self::object(12, 'NH-1002', ['name' => 'Rain pants']),
            13 => self::object(13, 'NH-1003', ['name' => 'Hidden'], viewable: false),
            21 => self::object(21, 'NH-2001', ['name' => 'Gaiters', 'price' => 39]),
        ];
    }

    public function testItListsTheUpdatedObjectsWithTheValuesOfTheFieldsTheFileWrote(): void
    {
        $this->runs->save($this->endedRun());

        $answer = $this->service()->objects(AdHocRunFixtures::FILE_ID, 'updated', 1, 50);

        $this->assertSame(3, $answer->getTotalItems(), 'every object the run updated counts');
        $this->assertSame(
            [['key' => 'name', 'label' => 'Name'], ['key' => 'price', 'label' => 'Price']],
            $answer->getFields()
        );
        $this->assertSame(
            [
                [11, '/Products/Outdoor/NH-1001', ['Trail jacket', '189.9']],
                [12, '/Products/Outdoor/NH-1002', ['Rain pants', null]],
            ],
            array_map(
                static fn (array $item): array => [$item['id'], $item['fullPath'], $item['values']],
                $answer->getItems()
            ),
            'an object the user may no longer see is left out'
        );
    }

    public function testItListsTheCreatedObjectsApart(): void
    {
        $this->runs->save($this->endedRun());

        $answer = $this->service()->objects(AdHocRunFixtures::FILE_ID, 'created', 1, 50);

        $this->assertSame(1, $answer->getTotalItems());
        $this->assertSame([21], array_column($answer->getItems(), 'id'));
    }

    public function testItListsAPageAtATime(): void
    {
        $this->runs->save($this->endedRun());

        $answer = $this->service()->objects(AdHocRunFixtures::FILE_ID, 'updated', 2, 1);

        $this->assertSame(3, $answer->getTotalItems());
        $this->assertSame([12], array_column($answer->getItems(), 'id'));
    }

    public function testAnObjectDeletedSinceIsLeftOut(): void
    {
        $this->runs->save($this->endedRun());
        unset($this->objects[12]);

        $answer = $this->service()->objects(AdHocRunFixtures::FILE_ID, 'updated', 1, 50);

        $this->assertSame([11], array_column($answer->getItems(), 'id'));
    }

    public function testTheExportHoldsEveryObjectOfTheKindWithItsValues(): void
    {
        $this->runs->save($this->endedRun());

        $csv = $this->service()->objectsCsv(AdHocRunFixtures::FILE_ID, 'updated');

        $this->assertSame(
            "data-importer.ad-hoc-import.check.column.id,data-importer.ad-hoc-import.check.column.path,Name,Price\n"
            . "11,/Products/Outdoor/NH-1001,\"Trail jacket\",189.9\n"
            . "12,/Products/Outdoor/NH-1002,\"Rain pants\",\n",
            $csv
        );
    }

    public function testARunStillGoingOnHasNoList(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['updatedIds' => [11]]));

        $this->expectException(NotFoundHttpException::class);
        $this->service()->objects(AdHocRunFixtures::FILE_ID, 'updated', 1, 50);
    }

    public function testAnotherUsersRunHasNoList(): void
    {
        $this->runs->save($this->endedRun(['userId' => AdHocRunFixtures::USER_ID + 1]));

        $this->expectException(NotFoundHttpException::class);
        $this->service()->objectsCsv(AdHocRunFixtures::FILE_ID, 'updated');
    }

    public function testOnlyUpdatedAndCreatedAreListed(): void
    {
        $this->runs->save($this->endedRun());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->objects(AdHocRunFixtures::FILE_ID, 'failed', 1, 50);
    }

    /**
     * @param array<string, mixed> $given
     */
    private function endedRun(array $given = []): RunRecord
    {
        return AdHocRunFixtures::run([
            'updatedIds' => [11, 13, 12],
            'createdIds' => [21],
            'fields' => self::FIELDS,
            ...$given,
        ])->finished(1800000000);
    }

    private function service(): AdHocImportRunObjectsService
    {
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn(AdHocRunFixtures::user(false));
        $resolver = $this->createStub(ConcreteObjectResolverInterface::class);
        $resolver->method('getById')->willReturnCallback(fn (int $id): ?Concrete => $this->objects[$id] ?? null);

        return new AdHocImportRunObjectsService($security, $this->runs, $resolver, AdHocRunFixtures::dryRunMessages());
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function object(int $id, string $key, array $values, bool $viewable = true): Concrete
    {
        $object = new class($values, $id, 3, $key) extends AdHocTestObject {
            public bool $viewable = true;

            public function isAllowed(string $type, ?User $user = null): bool
            {
                return $type === 'view' && $this->viewable;
            }
        };
        $object->viewable = $viewable;
        $object->setPath('/Products/Outdoor/');

        return $object;
    }
}
