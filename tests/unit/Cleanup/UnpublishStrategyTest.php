<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\Cleanup;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Cleanup\UnpublishStrategy;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;

class UnpublishStrategyTest extends Unit
{
    public function testPublishedElementIsUnpublishedAndSaved(): void
    {
        $element = $this->createMock(Concrete::class);
        $element->method('isPublished')->willReturn(true);
        $element->expects($this->once())->method('setPublished')->with(false);
        $element->expects($this->once())->method('save');

        $strategy = new UnpublishStrategy();
        $this->assertTrue($strategy->needsCleanup($element));
        $strategy->doCleanup($element);
    }

    public function testUnpublishedElementIsNotSavedAgain(): void
    {
        $element = $this->createMock(Concrete::class);
        $element->method('isPublished')->willReturn(false);
        $element->expects($this->never())->method('setPublished');
        $element->expects($this->never())->method('save');

        $strategy = new UnpublishStrategy();
        $this->assertFalse($strategy->needsCleanup($element));
        $strategy->doCleanup($element);
    }

    public function testElementWithoutPublishedStateNeedsNoCleanup(): void
    {
        $element = $this->createMock(Asset::class);
        $element->expects($this->never())->method('save');

        $strategy = new UnpublishStrategy();
        $this->assertFalse($strategy->needsCleanup($element));
        $strategy->doCleanup($element);
    }
}
