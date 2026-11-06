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
            $panel->addChild($localizedFields);

            $bricks = new ClassDefinition\Data\Objectbricks();
            $bricks->setName('bricks');
            $bricks->setAllowedTypes([self::BRICK_NAME, self::LOCALIZED_BRICK_NAME]);
            $panel->addChild($bricks);

            $otherBricks = new ClassDefinition\Data\Objectbricks();
            $otherBricks->setName('otherBricks');
            $otherBricks->setAllowedTypes([self::BRICK_NAME]);
            $panel->addChild($otherBricks);

            $related = new ClassDefinition\Data\ManyToOneRelation();
            $related->setName('related');
            $related->setTitle('related');
            $related->setObjectsAllowed(true);
            $panel->addChild($related);

            $relatedMany = new ClassDefinition\Data\ManyToManyObjectRelation();
            $relatedMany->setName('relatedMany');
            $relatedMany->setTitle('relatedMany');
            $panel->addChild($relatedMany);

            $relatedElements = new ClassDefinition\Data\ManyToManyRelation();
            $relatedElements->setName('relatedElements');
            $relatedElements->setTitle('relatedElements');
            $relatedElements->setObjectsAllowed(true);
            $panel->addChild($relatedElements);

            $colors = new ClassDefinition\Data\Multiselect();
            $colors->setName('colors');
            $colors->setTitle('colors');
            $colors->setOptions([['key' => 'red', 'value' => 'red']]);
            $panel->addChild($colors);

            $secret = new ClassDefinition\Data\Password();
            $secret->setName('secret');
            $secret->setTitle('secret');
            $panel->addChild($secret);

            $website = new ClassDefinition\Data\Link();
            $website->setName('website');
            $website->setTitle('website');
            $panel->addChild($website);

            $notes = new ClassDefinition\Data\Textarea();
            $notes->setName('notes');
            $notes->setTitle('notes');
            $panel->addChild($notes);

            $state = new ClassDefinition\Data\Select();
            $state->setName('state');
            $state->setTitle('state');
            $state->setOptions([['key' => 'red', 'value' => 'red']]);
            $panel->addChild($state);

            $weight = new ClassDefinition\Data\QuantityValue();
            $weight->setName('weight');
            $weight->setTitle('weight');
            $panel->addChild($weight);

            $articleNo = new ClassDefinition\Data\Numeric();
            $articleNo->setName('articleNo');
            $articleNo->setTitle('articleNo');
            $panel->addChild($articleNo);

            $active = new ClassDefinition\Data\Checkbox();
            $active->setName('active');
            $active->setTitle('active');
            $panel->addChild($active);

            $releaseDate = new ClassDefinition\Data\Date();
            $releaseDate->setName('releaseDate');
            $releaseDate->setTitle('releaseDate');
            $panel->addChild($releaseDate);
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
        }

        $definition = new Objectbrick\Definition();
        $definition->setKey($name);
        $definition->setLayoutDefinitions($panel);
        $definition->setClassDefinitions($classDefinitions);
        $definition->save();
    }
}
