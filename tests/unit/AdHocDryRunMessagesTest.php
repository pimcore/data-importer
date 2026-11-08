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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Model\User;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * The dry run's row errors with the bundle's English texts.
 */
class AdHocDryRunMessagesTest extends Unit
{
    /**
     * @dataProvider otherRowErrors
     */
    public function testEveryOtherRowErrorIsSaid(
        RowMatchOutcome $outcome,
        string $expected,
        ?int $firstRow = null
    ): void {
        $message = static::englishMessages()->rowError(
            new RowMatch($outcome, 'A/1', firstRow: $firstRow),
            'Object ID',
            '/target',
            '/new',
            new User()
        );

        $this->assertSame($expected, $message);
    }

    public static function otherRowErrors(): array
    {
        return [
            'invalid match value' => [
                RowMatchOutcome::InvalidValue,
                'Object ID “A/1” is no value objects can be matched by',
            ],
            'unknown object id' => [RowMatchOutcome::UnknownObjectId, 'No object has the ID A/1'],
            'other class' => [
                RowMatchOutcome::OtherClass,
                'The ID A/1 belongs to a folder or an object of another class',
            ],
            'key taken in the file' => [
                RowMatchOutcome::KeyTaken,
                'Row 3 already creates an object named A-1',
                3,
            ],
        ];
    }

    public static function englishMessages(): DryRunMessages
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource(
            'yaml',
            __DIR__ . '/../../src/Resources/translations/studio.en.yaml',
            'en',
            'studio'
        );

        return new DryRunMessages($translator);
    }
}
