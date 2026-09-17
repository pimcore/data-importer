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
use Pimcore\Bundle\ChangeControlBundle\Merge\NodeKind;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigShape;

class ImportConfigShapeTest extends Unit
{
    private ImportConfigShape $shape;

    protected function _before(): void
    {
        // the Change Control bundle is an optional integration this repository cannot install
        if (!enum_exists(NodeKind::class)) {
            static::markTestSkipped('Change Control bundle not installed');
        }

        $this->shape = new ImportConfigShape();
    }

    /**
     * The engine's default reads `type` and `path` as reference markers and stops walking;
     * here they are the adapter and bookkeeping, and the keys beside them must stay addressable.
     */
    public function testASectionCarryingTypeOrPathIsStillAContainer(): void
    {
        $general = ['active' => true, 'type' => 'dataImporterDataObject', 'name' => 'car-import', 'path' => null];
        $loader = ['type' => 'asset', 'settings' => ['assetPath' => '/import/cars.json']];

        static::assertSame(NodeKind::Container, $this->shape->kindOf(['general'], $general));
        static::assertSame(NodeKind::Container, $this->shape->kindOf(['loaderConfig'], $loader));
    }

    public function testAListIsAtomic(): void
    {
        static::assertSame(NodeKind::Atomic, $this->shape->kindOf(['mappingConfig'], [['label' => 'a'], ['label' => 'b']]));
        static::assertSame(NodeKind::Atomic, $this->shape->kindOf(['mappingConfig', '0', 'dataSourceIndex'], ['mileage']));
    }

    public function testScalarsAndEmptyArraysAreAtomic(): void
    {
        static::assertSame(NodeKind::Atomic, $this->shape->kindOf(['general', 'description'], 'Nightly import'));
        static::assertSame(NodeKind::Atomic, $this->shape->kindOf(['general', 'active'], true));
        static::assertSame(NodeKind::Atomic, $this->shape->kindOf(['executionConfig'], []));
    }
}
