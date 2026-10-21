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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Preview;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Preview\NoPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Schema\CalculateTransformationResultTypeParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\CopyPreviewParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\LoadPreviewParameters;

class PreviewScopeTest extends Unit
{
    public function testNoChangeSetIsNoScope(): void
    {
        static::assertNull(PreviewScope::fromRequest('', 'agent-session:7'));
    }

    public function testAnEmptyContextIsNone(): void
    {
        static::assertEquals(new PreviewScope('cs-1'), PreviewScope::fromRequest('cs-1', ''));
    }

    public function testTheRequestParametersCarryTheContext(): void
    {
        $expected = new PreviewScope('cs-1', 'agent-session:7');

        static::assertEquals($expected, (new LoadPreviewParameters(null, 0, 'cs-1', 'agent-session:7'))->getPreviewScope());
        static::assertEquals($expected, (new CopyPreviewParameters(null, 'cs-1', 'agent-session:7'))->getPreviewScope());
        static::assertEquals(
            $expected,
            (new CalculateTransformationResultTypeParameters([], 'cs-1', 'agent-session:7'))->getPreviewScope()
        );
        static::assertNull((new LoadPreviewParameters())->getPreviewScope());
    }

    /** without Change Control there are no change sets to bind a scope to */
    public function testWithoutChangeControlNoScopeIsBound(): void
    {
        static::assertNull((new NoPreviewScopeResolver())->proposal('car-import', new PreviewScope('cs-1')));
    }
}
