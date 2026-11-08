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
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceVisibilityInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ObjectField;
use Pimcore\Model\Element\ElementInterface;

/**
 * An element an operator reads through another element is left out where the caller's lookup does not let it view it.
 */
class ObjectFieldReferenceVisibilityTest extends Unit
{
    private const PARENT_PATH = '/shared/parent';

    public function testTheFullPathOfAnElementTheLookupLetsViewIsRead(): void
    {
        $this->assertSame(self::PARENT_PATH, $this->parentPath(visible: true));
    }

    public function testAnElementTheLookupDoesNotLetViewIsLeftOut(): void
    {
        $this->assertNull($this->parentPath(visible: false));
    }

    public function testWithoutAScopeTheFullPathIsRead(): void
    {
        $operator = $this->operator();

        $this->assertSame(self::PARENT_PATH, $operator->process($this->child()));
    }

    private function parentPath(bool $visible): mixed
    {
        $scope = new MappingApplicationScope();
        $operator = $this->operator();
        $operator->setMappingApplicationScope($scope);

        return $scope->run(
            new class($visible) implements ReferenceVisibilityInterface {
                public function __construct(private readonly bool $visible)
                {
                }

                public function find(ReferenceQuery $query): ?ElementInterface
                {
                    return null;
                }

                public function isVisible(ElementInterface $element): bool
                {
                    return $this->visible;
                }
            },
            fn (): mixed => $operator->process($this->child())
        );
    }

    private function operator(): ObjectField
    {
        $operator = new ObjectField($this->createMock(ApplicationLogger::class));
        $operator->setSettings(['attribute' => 'parent']);

        return $operator;
    }

    private function child(): ElementInterface
    {
        $parent = $this->createMock(ElementInterface::class);
        $parent->method('getFullPath')->willReturn(self::PARENT_PATH);
        $child = $this->createMock(ElementInterface::class);
        $child->method('getParent')->willReturn($parent);

        return $child;
    }
}
