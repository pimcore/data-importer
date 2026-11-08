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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FieldAccess;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;

class AdHocFieldAccessTest extends Unit
{
    public function testAGroupsMandatoryMarkLeavesTheSharedDefinitionAsItIs(): void
    {
        $shared = new Input();
        $relation = new KeyGroupRelation();
        $relation->setMandatory(true);

        $inGroup = FieldAccess::inGroup($shared, $relation);

        $this->assertTrue($inGroup->getMandatory());
        $this->assertFalse($shared->getMandatory(), 'Pimcore caches the definition per key, for every group');
    }

    public function testAKeyMandatoryInItsDefinitionStaysSoInAGroupThatDoesNotMarkIt(): void
    {
        $definition = new Input();
        $definition->setMandatory(true);

        $this->assertTrue(FieldAccess::inGroup($definition, new KeyGroupRelation())->getMandatory());
    }

    public function testANeitherMandatoryKeyStaysOptional(): void
    {
        $this->assertFalse(FieldAccess::inGroup(new Input(), new KeyGroupRelation())->getMandatory());
    }
}
