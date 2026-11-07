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
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;

class AdHocTransformationAllowListTest extends Unit
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function stepsAnAdHocImportDoesNotOffer(): iterable
    {
        yield 'combining columns' => [['type' => 'combine']];
        yield 'importing files' => [['type' => 'importAsset']];
        yield 'no type' => [['settings' => []]];
        yield 'no object' => ['trim'];
        yield 'loading an object by an attribute' => [
            ['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'attribute']],
        ];
        yield 'loading unpublished objects' => [
            ['type' => 'loadDataObject', 'settings' => ['loadUnpublished' => true]],
        ];
        yield 'a setting of another operator' => [['type' => 'trim', 'settings' => ['delimiter' => ',']]];
        yield 'a setting that is no text' => [['type' => 'explode', 'settings' => ['delimiter' => [',']]]];
        yield 'settings that are no object' => [['type' => 'trim', 'settings' => 'both']];
        yield 'a trim mode there is none of' => [['type' => 'trim', 'settings' => ['mode' => 'middle']]];
    }

    /**
     * @dataProvider stepsAnAdHocImportDoesNotOffer
     */
    public function testAStepOutsideTheAllowListIsRefused(mixed $step): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transformation 3');

        TransformationAllowList::step($step, 3);
    }

    public function testAStepKeepsItsSettingsAndTheLoadingOnesDefaultToTheId(): void
    {
        $replace = ['type' => 'stringReplace', 'settings' => ['search' => ',', 'replace' => '.']];
        $this->assertSame($replace, TransformationAllowList::step($replace, 1));
        $this->assertSame(
            ['type' => 'loadAsset', 'settings' => ['loadStrategy' => 'id']],
            TransformationAllowList::step(['type' => 'loadAsset'], 1)
        );
        $this->assertSame(
            ['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']],
            TransformationAllowList::step(['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']], 1)
        );
        $this->assertSame(['type' => 'trim', 'settings' => []], TransformationAllowList::step(['type' => 'trim'], 1));
    }
}
