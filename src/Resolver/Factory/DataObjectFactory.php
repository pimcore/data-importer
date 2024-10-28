<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Factory;

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Factory;

class DataObjectFactory implements FactoryInterface
{
    /**
     * @var string
     */
    protected $subType;

    /**
     * @var Factory
     */
    protected $modelFactory;

    public function __construct(Factory $modelFactory)
    {
        $this->modelFactory = $modelFactory;
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
