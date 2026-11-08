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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
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

    /**
     * @dataProvider valueErrors
     */
    public function testEveryValueErrorIsSaid(ValueError $error, string $expected): void
    {
        $this->assertSame($expected, static::englishMessages()->valueError($error, 'Weight', 'heavy', new User()));
    }

    public static function valueErrors(): array
    {
        return [
            'not a number' => [ValueError::NotANumber, 'Weight “heavy” is no number'],
            'not a date' => [ValueError::NotADate, 'Weight “heavy” is no date'],
            'not yes or no' => [ValueError::NotYesOrNo, 'Weight “heavy” is neither yes nor no'],
            'not an option' => [ValueError::NotAnOption, 'Weight “heavy” is no option of Weight'],
            'required' => [ValueError::Required, 'Weight is required'],
            'invalid' => [ValueError::Invalid, 'Weight “heavy” does not fit the field'],
        ];
    }

    public function testEveryValueErrorHasItsText(): void
    {
        $this->assertSame(
            array_map(static fn (ValueError $error): string => $error->name, ValueError::cases()),
            array_map(static fn (array $case): string => $case[0]->name, array_values(self::valueErrors()))
        );
    }

    public function testTheObjectIdAndColumnLabelsAreSaid(): void
    {
        $messages = static::englishMessages();

        $this->assertSame('Object ID', $messages->objectIdLabel(new User()));
        $this->assertSame('Row', $messages->columnName('row', new User()));
        $this->assertSame('Error', $messages->columnName('error', new User()));
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
