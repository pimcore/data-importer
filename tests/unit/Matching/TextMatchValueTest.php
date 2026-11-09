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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Matching;

use Codeception\Test\Unit;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\TextMatchValue;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\RowMatcherOptionsProvider;
use Pimcore\Model\DataObject\ClassDefinition\Data\Country;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Language;
use Pimcore\Model\DataObject\ClassDefinition\Data\OptionsProviderInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Select;
use Pimcore\Model\DataObject\ClassDefinition\Data\Textarea;
use Pimcore\Model\DataObject\ClassDefinition\Data\User as UserField;
use Pimcore\Model\User;
use Pimcore\Model\User\Role;

class TextMatchValueTest extends Unit
{
    private const ALLOWED = 'red';

    private const NOT_ALLOWED = 'blue';

    public function testAnInputTakesWhatItsColumnHoldsCountingCharacters(): void
    {
        $field = new Input();
        $field->setColumnLength(3);

        $this->assertTrue(TextMatchValue::fits('abc', $field));
        $this->assertTrue(TextMatchValue::fits('äöü', $field));
        $this->assertFalse(TextMatchValue::fits('abcd', $field));
    }

    public function testAnInputTakesOnlyWhatItsRegexAccepts(): void
    {
        $field = new Input();
        $field->setRegex('^[A-Z]+$');

        $this->assertTrue(TextMatchValue::fits('ABC', $field));
        $this->assertFalse(TextMatchValue::fits('abc', $field));
    }

    public function testAnInputsRegexFlagsCount(): void
    {
        $field = new Input();
        $field->setRegex('^[A-Z]+$');
        $field->setRegexFlags(['i']);

        $this->assertTrue(TextMatchValue::fits('abc', $field));
        $this->assertFalse(TextMatchValue::fits('abc1', $field));
    }

    public function testATextareaTakesWhatItsMaximumLengthAllows(): void
    {
        $field = new Textarea();
        $field->setMaxLength(3);

        $this->assertTrue(TextMatchValue::fits('abc', $field));
        $this->assertFalse(TextMatchValue::fits('abcd', $field));
    }

    public function testATextareaWithoutAMaximumLengthTakesAnyLength(): void
    {
        $this->assertTrue(TextMatchValue::fits(str_repeat('a', 5000), new Textarea()));
    }

    public function testASelectTakesOnlyItsOptionsAlsoWhenItDoesNotEnforceThem(): void
    {
        $field = $this->select();

        $this->assertFalse($field->isEnforceValidation());
        $this->assertTrue(TextMatchValue::fits(self::ALLOWED, $field));
        $this->assertFalse(TextMatchValue::fits(self::NOT_ALLOWED, $field));
    }

    public function testASelectThatEnforcesItsOptionsTakesOnlyThem(): void
    {
        $field = $this->select();
        $field->setEnforceValidation(true);

        $this->assertTrue(TextMatchValue::fits(self::ALLOWED, $field));
        $this->assertFalse(TextMatchValue::fits(self::NOT_ALLOWED, $field));
    }

    public function testASelectComparesAnOptionsValueNotItsLabelAndExactly(): void
    {
        $field = new Select();
        $field->setOptions([['key' => 'Label', 'value' => 'stored'], ['key' => 'One', 'value' => 1]]);

        $this->assertTrue(TextMatchValue::fits('stored', $field));
        $this->assertTrue(TextMatchValue::fits('1', $field));
        $this->assertFalse(TextMatchValue::fits('Label', $field));
        $this->assertFalse(TextMatchValue::fits('STORED', $field));
        $this->assertFalse(TextMatchValue::fits('1.0', $field));
    }

    public function testASelectGetsItsOptionsFromItsProvider(): void
    {
        $field = new Select();
        $field->setName('state');
        $field->setOptionsProviderType(OptionsProviderInterface::TYPE_CLASS);
        $field->setOptionsProviderClass(RowMatcherOptionsProvider::class);

        $this->assertTrue(TextMatchValue::fits(self::ALLOWED, $field));
        $this->assertFalse(TextMatchValue::fits(self::NOT_ALLOWED, $field));
    }

    public function testASelectWithoutOptionsTakesWhatItsColumnHolds(): void
    {
        $field = new Select();
        $field->setOptions([]);
        $field->setColumnLength(3);

        $this->assertTrue(TextMatchValue::fits('abc', $field));
        $this->assertFalse(TextMatchValue::fits('abcd', $field));
    }

    public function testASelectWhoseOptionsDependOnTheObjectLeavesTheCheckToTheSave(): void
    {
        $field = $this->select();
        $field->dynamicOptions = true;

        $this->assertTrue(TextMatchValue::fits(self::NOT_ALLOWED, $field));
    }

    public function testAUserFieldTakesOnlyTheIdOfAUserThatExists(): void
    {
        $user = (new User())->setName('text-match-value-' . uniqid());
        $user->save();

        try {
            $id = (string) $user->getId();

            $this->assertTrue(TextMatchValue::fits($id, $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits('abc', $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits($id . 'abc', $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits('0' . $id, $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits($id . '0000', $this->named(new UserField())));
        } finally {
            $user->delete();
        }
    }

    public function testAUserFieldSeesUsersCreatedAndDeletedAfterItsFirstCheck(): void
    {
        $field = $this->named(new UserField());
        $this->assertFalse(TextMatchValue::fits('abc', $field));

        $user = (new User())->setName('text-match-value-' . uniqid());
        $user->save();
        $id = (string) $user->getId();

        try {
            $this->assertTrue(TextMatchValue::fits($id, $field));
        } finally {
            $user->delete();
        }

        // what a worker does between two messages
        RuntimeCache::clear();
        $this->assertFalse(TextMatchValue::fits($id, $field));
    }

    public function testAUserFieldTakesNeitherARoleNorTheSystemUser(): void
    {
        $role = (new Role())->setName('text-match-value-' . uniqid());
        $role->save();

        try {
            $this->assertFalse(TextMatchValue::fits((string) $role->getId(), $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits('0', $this->named(new UserField())));
            $this->assertFalse(TextMatchValue::fits('-1', $this->named(new UserField())));
        } finally {
            $role->delete();
        }
    }

    public function testALanguageFieldTakesOnlyALanguage(): void
    {
        $this->assertTrue(TextMatchValue::fits('en', $this->named(new Language())));
        $this->assertFalse(TextMatchValue::fits('xx', $this->named(new Language())));
    }

    public function testACountryFieldTakesOnlyACountry(): void
    {
        $this->assertTrue(TextMatchValue::fits('AT', $this->named(new Country())));
        $this->assertFalse(TextMatchValue::fits('XX', $this->named(new Country())));
    }

    private function named(Select $field): Select
    {
        $field->setName('field');

        return $field;
    }

    private function select(): Select
    {
        $field = new Select();
        $field->setOptions([['key' => 'Red', 'value' => self::ALLOWED]]);

        return $field;
    }
}
