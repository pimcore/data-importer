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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionService;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class AdHocImportTargetsTest extends Unit
{
    /**
     * A branch records no block or field collection, so the import must not offer one to write: the ad hoc import
     * takes only the targets the mapping offers, for a column and for a configuration's direct target alike.
     */
    public function testNoTargetTheImportOffersIsABlockOrAFieldCollection(): void
    {
        $types = new TransformationDataTypeService();
        $requested = (new \ReflectionClassConstant(MappingSuggestionService::class, 'TRANSFORMATION_RESULT_TYPES'))
            ->getValue();

        $offered = [];
        foreach ($requested as $requestedTypes) {
            foreach ((array) $requestedTypes as $type) {
                $offered = [...$offered, ...$types->getPimcoreTypesByTransformationTargetType($type)];
            }
        }

        $this->assertContains('input', $offered);
        $this->assertSame([], array_values(array_intersect($offered, ['block', 'fieldcollections'])));
    }

    /**
     * Nor a field inside one: only localized fields and object bricks are opened up for their fields.
     */
    public function testNoFieldInsideABlockOrAFieldCollectionIsOffered(): void
    {
        $types = new TransformationDataTypeService();
        $collect = new \ReflectionMethod(TransformationDataTypeService::class, 'addTypesToAttributesArray');

        $inBlock = (new Data\Block())->setName('specs');
        $inBlock->setChildren([(new Data\Input())->setName('specText')]);
        $collections = (new Data\Fieldcollections())->setName('features');
        $localized = new Data\Localizedfields();
        $localized->setName('localizedfields');
        $localized->setChildren([(new Data\Input())->setName('title')]);

        $attributes = [];
        foreach ([$inBlock, $collections, $localized] as $definition) {
            $collect->invokeArgs($types, [$definition, TransformationDataTypeService::DEFAULT_TYPE, &$attributes]);
        }

        $this->assertSame(['title'], array_keys($attributes));
    }
}
