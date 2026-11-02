<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Factory;

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Factory;

/**
 * @internal
 */
final class DataObjectFactory implements FactoryInterface
{
    private string $subType;

    public function __construct(private readonly Factory $modelFactory)
    {
    }

    public function setSubType(string $subType): void
    {
        $this->subType = $subType;
    }

    /**
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function createNewElement(): ElementInterface
    {
        $class = ClassDefinition::getById($this->subType);
        if (empty($class)) {
            throw new InvalidConfigurationException("Class `{$this->subType}` not found.");
        }

        $className = '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());
        $element = $this->modelFactory->build($className);

        if (!($element instanceof ElementInterface)) {
            throw new InvalidConfigurationException(
                "Object of class `{$this->subType}` could not be created."
            );
        }

        $element->setKey(uniqid('import-', true));

        return $element;
    }
}
