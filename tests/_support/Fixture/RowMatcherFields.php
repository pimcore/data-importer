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

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;

/**
 * One field of each type the row matcher's tests accept or refuse.
 */
final class RowMatcherFields
{
    public static function addTo(Panel $panel): void
    {
        $related = new Data\ManyToOneRelation();
        $related->setObjectsAllowed(true);
        self::add($panel, $related, 'related');

        self::add($panel, new Data\ManyToManyObjectRelation(), 'relatedMany');

        $relatedElements = new Data\ManyToManyRelation();
        $relatedElements->setObjectsAllowed(true);
        self::add($panel, $relatedElements, 'relatedElements');

        $colors = new Data\Multiselect();
        $colors->setOptions([['key' => 'red', 'value' => 'red']]);
        self::add($panel, $colors, 'colors');

        self::add($panel, new Data\Password(), 'secret');
        self::add($panel, new Data\Wysiwyg(), 'description');
        self::add($panel, new Data\Slider(), 'rating');
        self::add($panel, new Data\Link(), 'website');
        self::add($panel, new Data\Textarea(), 'notes');

        $state = new Data\Select();
        $state->setOptions([['key' => 'red', 'value' => 'red']]);
        self::add($panel, $state, 'state');

        self::add($panel, new Data\QuantityValue(), 'weight');
        self::add($panel, new Data\Numeric(), 'articleNo');

        $stock = new Data\Numeric();
        $stock->setInteger(true);
        self::add($panel, $stock, 'stock');

        $price = new Data\Numeric();
        $price->setDecimalSize(10);
        $price->setDecimalPrecision(2);
        self::add($panel, $price, 'price');

        self::add($panel, new Data\Checkbox(), 'active');
        self::add($panel, new Data\Date(), 'releaseDate');
    }

    private static function add(Panel $panel, Data $field, string $name): void
    {
        $field->setName($name);
        $field->setTitle($name);
        $panel->addChild($field);
    }
}
