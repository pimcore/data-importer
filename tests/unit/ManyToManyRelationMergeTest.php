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

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ManyToManyRelation;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;

/**
 * Merge mode with unsaved elements, as a reference lookup may return them.
 */
class ManyToManyRelationMergeTest extends Unit
{
    public function testMergeKeepsEveryUnsavedObject(): void
    {
        $this->assertMergeKeepsEveryUnsavedElement('plainLinks');
    }

    public function testMergeKeepsEveryUnsavedElement(): void
    {
        $this->assertMergeKeepsEveryUnsavedElement('relatedElements');
    }

    private function assertMergeKeepsEveryUnsavedElement(string $field): void
    {
        $saved = $this->element(7);
        $current = $this->element();
        $first = $this->element();
        $second = $this->element();
        $container = $this->container();
        $container->values[$field] = [$saved, $current];

        $target = new ManyToManyRelation();
        $target->setSettings(['fieldName' => $field, 'overwriteMode' => 'merge']);
        $target->assignData($container, [$this->element(7), $current, $first, $second, $first]);

        // a saved element is told apart by id, an unsaved one by instance
        $this->assertSame([$saved, $current, $first, $second], $container->values[$field]);
    }

    private function element(?int $id = null): DataObject
    {
        $element = new DataObject\Folder();
        $element->setId($id);
        $element->setKey('element-' . ($id ?? 'unsaved'));

        return $element;
    }

    private function container(): Concrete
    {
        $class = new ClassDefinition();
        $plainLinks = new ClassDefinition\Data\ManyToManyObjectRelation();
        $plainLinks->setName('plainLinks');
        $class->addFieldDefinition('plainLinks', $plainLinks);
        $relatedElements = new ClassDefinition\Data\ManyToManyRelation();
        $relatedElements->setName('relatedElements');
        $class->addFieldDefinition('relatedElements', $relatedElements);

        $container = new class() extends Concrete {
            /** @var array<string, list<DataObject>> */
            public array $values = [];

            public function getPlainLinks(?string $language = null): array
            {
                return $this->values['plainLinks'] ?? [];
            }

            public function setPlainLinks(array $value, ?string $language = null): static
            {
                $this->values['plainLinks'] = $value;

                return $this;
            }

            public function getRelatedElements(?string $language = null): array
            {
                return $this->values['relatedElements'] ?? [];
            }

            public function setRelatedElements(array $value, ?string $language = null): static
            {
                $this->values['relatedElements'] = $value;

                return $this;
            }
        };
        $container->setClass($class);

        return $container;
    }
}
