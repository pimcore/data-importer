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
}
