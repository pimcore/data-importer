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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService as Types;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationCell;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\User;

/**
 * The container's load operators, which find a stored object and asset by ID and by full path.
 */
class AdHocTransformationLoadTest extends Unit
{
    // a 1×1 transparent PNG
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAAS'
        . 'UVORK5CYII=';

    protected UnitTester $tester;

    private DataObject\Folder $object;

    private Asset\Image $image;

    protected function _before(): void
    {
        $this->object = new DataObject\Folder();
        $this->object->setKey(uniqid('ad-hoc-load-', true));
        $this->object->setParentId(1);
        $this->object->save();

        $this->image = new Asset\Image();
        $this->image->setFilename(uniqid('ad-hoc-load-', true) . '.png');
        $this->image->setParentId(1);
        $this->image->setData((string) base64_decode(self::PNG, true));
        $this->image->save();
    }

    protected function _after(): void
    {
        $this->image->delete();
        $this->object->delete();
    }

    public function testAnObjectIsLoadedByItsIdAndByItsFullPath(): void
    {
        $path = $this->object->getRealFullPath();

        $this->assertSame(
            [[$path], [], []],
            $this->results(
                TransformationAllowList::LOAD_OBJECT,
                'id',
                [(string) $this->object->getId(), '999999999', '']
            )
        );
        $this->assertSame(
            [[$path], []],
            $this->results(TransformationAllowList::LOAD_OBJECT, 'path', [$path, '/no/such/object'])
        );
    }

    public function testAnAssetIsLoadedByItsIdAndByItsFullPath(): void
    {
        $path = $this->image->getRealFullPath();

        $this->assertSame(
            [[$path], []],
            $this->results(TransformationAllowList::LOAD_ASSET, 'id', [(string) $this->image->getId(), '999999999'])
        );
        $this->assertSame(
            [[$path], []],
            $this->results(TransformationAllowList::LOAD_ASSET, 'path', [$path, '/no/such/image.png'])
        );
    }

    /**
     * The step's values; a value that finds nothing is an empty cell, not a failed one.
     *
     * @param list<string> $values
     *
     * @return list<list<string>>
     */
    private function results(string $load, string $strategy, array $values): array
    {
        $chain = $this->tester->grabService(TransformationChain::class);
        $this->assertInstanceOf(TransformationChain::class, $chain);
        $step = TransformationAllowList::step(['type' => $load, 'settings' => ['loadStrategy' => $strategy]], 1);
        $type = $load === TransformationAllowList::LOAD_ASSET ? Types::ASSET : Types::DATA_OBJECT;

        $admin = new User();
        $admin->setAdmin(true);
        $result = $chain->run($values, [$step], [$type], $admin);

        $this->assertTrue($result->getFits());
        $cells = $result->getResults();
        $this->assertSame(
            array_fill(0, count($values), false),
            array_map(static fn (AdHocImportTransformationCell $cell): bool => $cell->getFailed(), $cells)
        );

        return array_map(static fn (AdHocImportTransformationCell $cell): array => $cell->getItems(), $cells);
    }
}
