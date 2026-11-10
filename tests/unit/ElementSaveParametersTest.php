<?php declare(strict_types=1);

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
use Pimcore\Bundle\DataImporterBundle\Processing\ElementSaveParameters;

class ElementSaveParametersTest extends Unit
{
    public function testConfiguredVersionNoteBecomesSaveParameter(): void
    {
        $saveParameters = ElementSaveParameters::fromProcessingConfig(['versionNote' => 'Imported by ERP feed']);

        static::assertSame(['versionNote' => 'Imported by ERP feed'], $saveParameters);
    }

    public function testVersionNoteIsTrimmed(): void
    {
        $saveParameters = ElementSaveParameters::fromProcessingConfig(['versionNote' => "  Imported by ERP feed \n"]);

        static::assertSame(['versionNote' => 'Imported by ERP feed'], $saveParameters);
    }

    /**
     * @dataProvider provideProcessingConfigsWithoutVersionNote
     */
    public function testNoSaveParametersWithoutVersionNote(array $processingConfig): void
    {
        static::assertSame([], ElementSaveParameters::fromProcessingConfig($processingConfig));
    }

    public static function provideProcessingConfigsWithoutVersionNote(): array
    {
        return [
            'setting missing' => [[]],
            'other settings only' => [['executionType' => 'parallel', 'disableVersioning' => true]],
            'null' => [['versionNote' => null]],
            'empty string' => [['versionNote' => '']],
            'whitespace only' => [['versionNote' => "  \t\n"]],
            'not a string' => [['versionNote' => ['Imported']]],
        ];
    }
}
