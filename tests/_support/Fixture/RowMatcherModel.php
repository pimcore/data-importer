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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Objectbrick;

/**
 * The classes and object bricks the row matcher's tests search in.
 */
final class RowMatcherModel
{
    public const CLASS_NAME = 'DiRowMatcherClass';

    public const BRICK_NAME = 'DiRowMatcherBrick';

    public const OTHER_BRICK_NAME = 'DiRowMatcherOtherBrick';

    public const LOCALIZED_BRICK_NAME = 'DiRowMatcherLocalizedBrick';

    // a reserved word in SQL, which core does not forbid as a brick's name
    public const RESERVED_WORD_BRICK_NAME = 'Order';

    public const INHERITING_CLASS_NAME = 'DiRowMatcherInheritingClass';

    public static function createClass(): ClassDefinition
    {
        $definition = self::createClassNamed(self::CLASS_NAME);
        self::createBrick(self::BRICK_NAME, [
            ['classname' => self::CLASS_NAME, 'fieldname' => 'bricks'],
            ['classname' => self::CLASS_NAME, 'fieldname' => 'otherBricks'],
        ]);
        self::createBrick(self::OTHER_BRICK_NAME, []);
        self::createBrick(
            self::LOCALIZED_BRICK_NAME,
            [['classname' => self::CLASS_NAME, 'fieldname' => 'bricks']],
            true
        );
        self::createBrick(self::RESERVED_WORD_BRICK_NAME, [['classname' => self::CLASS_NAME, 'fieldname' => 'bricks']]);

        return $definition;
    }

    public static function createClassNamed(string $name): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');

        foreach (['sku', 'name'] as $field) {
            $input = new ClassDefinition\Data\Input();
            $input->setName($field);
            $input->setTitle($field);
            $panel->addChild($input);
        }

        if ($name === self::CLASS_NAME) {
            $title = new ClassDefinition\Data\Input();
            $title->setName('title');
            $title->setTitle('title');

            $localizedFields = new ClassDefinition\Data\Localizedfields();
            $localizedFields->setName('localizedfields');
            $localizedFields->addChild($title);

            $localizedNumber = new ClassDefinition\Data\Numeric();
            $localizedNumber->setName('localizedNumber');
            $localizedNumber->setTitle('localizedNumber');
            $localizedFields->addChild($localizedNumber);
            $panel->addChild($localizedFields);

            $bricks = new ClassDefinition\Data\Objectbricks();
            $bricks->setName('bricks');
            $bricks->setAllowedTypes([self::BRICK_NAME, self::LOCALIZED_BRICK_NAME, self::RESERVED_WORD_BRICK_NAME]);
            $panel->addChild($bricks);

            $otherBricks = new ClassDefinition\Data\Objectbricks();
            $otherBricks->setName('otherBricks');
            $otherBricks->setAllowedTypes([self::BRICK_NAME]);
            $panel->addChild($otherBricks);

            RowMatcherFields::addTo($panel);
        }

        $definition = new ClassDefinition();
        $definition->setName($name);
        $definition->setLayoutDefinitions($panel);
        if ($name === self::INHERITING_CLASS_NAME) {
            $definition->setAllowInherit(true);
            $definition->setAllowVariants(true);
        }
        $definition->save();

        return $definition;
    }

    /**
     * @param list<array{classname: string, fieldname: string}> $classDefinitions
     */
    private static function createBrick(string $name, array $classDefinitions, bool $localized = false): void
    {
        if (Objectbrick\Definition::getByKey($name) instanceof Objectbrick\Definition) {
            return;
        }

        $code = new ClassDefinition\Data\Input();
        $code->setName('code');
        $code->setTitle('code');

        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        if ($localized) {
            $localizedFields = new ClassDefinition\Data\Localizedfields();
            $localizedFields->setName('localizedfields');
            $localizedFields->addChild($code);
            $panel->addChild($localizedFields);
        } else {
            $panel->addChild($code);

            $number = new ClassDefinition\Data\Numeric();
            $number->setName('number');
            $number->setTitle('number');
            $panel->addChild($number);
        }

        $definition = new Objectbrick\Definition();
        $definition->setKey($name);
        $definition->setLayoutDefinitions($panel);
        $definition->setClassDefinitions($classDefinitions);
        $definition->save();
    }
}
