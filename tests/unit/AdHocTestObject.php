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

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;

/**
 * A data object of a class with a name and a price, holding its values in memory.
 */
class AdHocTestObject extends Concrete
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(public array $values = [], ?int $id = null, int $parentId = 3, string $key = 'SKU-1')
    {
        if ($id !== null) {
            $this->setId($id);
        }
        $this->setParentId($parentId);
        $this->setKey($key);
    }

    public function getClass(): ClassDefinition
    {
        $definition = new ClassDefinition();
        $definition->setName('Product');
        $definition->addFieldDefinition('name', new Data\Input());
        $definition->addFieldDefinition('price', new Data\Numeric());

        return $definition;
    }

    public function getClassName(): string
    {
        return 'Product';
    }

    public function get(string $fieldName, ?string $language = null): mixed
    {
        return $this->values[$fieldName] ?? null;
    }
}
