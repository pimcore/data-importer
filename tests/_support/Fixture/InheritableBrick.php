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

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Exception\InheritanceParentNotFoundException;
use Pimcore\Model\DataObject\Objectbrick\Data\AbstractData;
use Pimcore\Model\DataObject\Objectbrick\Definition;

/**
 * In-memory object brick whose getter mirrors the code Pimcore generates for an
 * inheritable field: an empty value falls back to getValueFromParent().
 */
class InheritableBrick extends AbstractData
{
    protected string $type = 'inheritableBrick';

    protected ?string $title = null;

    public function getDefinition(): Definition
    {
        $title = new Input();
        $title->setName('title');

        $definition = new Definition();
        $definition->setKey($this->type);
        $definition->addFieldDefinition('title', $title);

        return $definition;
    }

    public function getTitle(): ?string
    {
        $data = $this->title;
        if (DataObject::doGetInheritedValues($this->getObject())
            && $this->getDefinition()->getFieldDefinition('title')->isEmpty($data)) {
            try {
                return $this->getValueFromParent('title');
            } catch (InheritanceParentNotFoundException) {
                // no data from parent available, continue ...
            }
        }

        return $data;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }
}
