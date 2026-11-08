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
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\Objectbrick\DefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;

class AdHocImportClassificationStoreKeysTest extends Unit
{
    public function testTheKeysOfAClassificationStoreAreListedWithoutAnyTypeLeftOut(): void
    {
        $keys = new ClassificationStoreKeysResponse(
            [['keyId' => 4, 'groupId' => 2, 'keyName' => 'Width', 'id' => '2-4']],
            1
        );
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->once())->method('loadClassificationStoreKeys')
            ->with($this->callback(static fn (ClassificationStoreKeyParameters $parameters): bool =>
                $parameters->getClassId() === 'PROD' &&
                $parameters->getFieldName() === 'attributes' &&
                $parameters->getTransformationResultType() === '' &&
                $parameters->getSearchfilter() === 'wid' &&
                $parameters->getStart() === 20 &&
                $parameters->getLimit() === 10))
            ->willReturn($keys);

        $service = $this->service(
            creatableClasses: ['PROD'],
            suggestions: $this->createStub(MappingSuggestionServiceInterface::class),
            class: $this->classWithStore(),
            dataTypes: $dataTypes
        );

        $this->assertSame($keys, $service->loadClassificationStoreKeys('PROD', 'attributes', 'wid', 20, 10));
    }

    public function testAnEmptySearchListsEveryKey(): void
    {
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->once())->method('loadClassificationStoreKeys')
            ->with($this->callback(
                static fn (ClassificationStoreKeyParameters $parameters): bool =>
                    $parameters->getSearchfilter() === null
            ))
            ->willReturn(new ClassificationStoreKeysResponse([], 0));

        $this->service(
            creatableClasses: ['PROD'],
            suggestions: $this->createStub(MappingSuggestionServiceInterface::class),
            class: $this->classWithStore(),
            dataTypes: $dataTypes
        )->loadClassificationStoreKeys('PROD', 'attributes', '', 0, 15);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldsWithoutKeys(): iterable
    {
        yield 'a field that is no classification store' => ['sku'];
        yield 'a field the class does not have' => ['missing'];
    }

    /**
     * @dataProvider fieldsWithoutKeys
     */
    public function testTheKeysOfAFieldThatIsNoClassificationStoreAreNotFound(string $fieldName): void
    {
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->never())->method('loadClassificationStoreKeys');

        $this->expectException(NotFoundException::class);

        $this->service(
            creatableClasses: ['PROD'],
            suggestions: $this->createStub(MappingSuggestionServiceInterface::class),
            class: $this->classWithStore(),
            dataTypes: $dataTypes
        )->loadClassificationStoreKeys('PROD', $fieldName, null, 0, 15);
    }

    public function testTheKeysOfAClassTheUserMayNotCreateAreForbidden(): void
    {
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->never())->method('loadClassificationStoreKeys');

        $this->expectException(ForbiddenException::class);

        $this->service(
            creatableClasses: ['OTHER'],
            suggestions: $this->createStub(MappingSuggestionServiceInterface::class),
            class: $this->classWithStore(),
            dataTypes: $dataTypes
        )->loadClassificationStoreKeys('PROD', 'attributes', null, 0, 15);
    }

    private function classWithStore(): ClassDefinition
    {
        $store = new Classificationstore();
        $store->setName('attributes');

        $sku = new Input();
        $sku->setName('sku');
        $panel = new Panel();
        $panel->setName('untitled');
        $panel->setChildren([$sku, $store]);

        $class = new ClassDefinition();
        $class->setName('Product');
        $class->setLayoutDefinitions($panel);

        return $class;
    }

    private function service(
        array $creatableClasses,
        MappingSuggestionServiceInterface $suggestions,
        ClassDefinition $class,
        DataTypeServiceInterface $dataTypes
    ): AdHocImportMappingService {
        $classes = $this->createStub(ClassDefinitionServiceInterface::class);
        $classes->method('getClassDefinitionCollection')->willReturnCallback(
            static fn (bool $creatableOnly = false): array => AdHocCreatableClasses::listed(
                $creatableClasses,
                $creatableOnly
            )
        );

        $classResolver = $this->createStub(ClassDefinitionResolverInterface::class);
        $classResolver->method('getById')->willReturn($class);

        return new AdHocImportMappingService(
            $classes,
            $suggestions,
            $classResolver,
            $dataTypes,
            $this->createStub(DefinitionResolverInterface::class),
            AdHocTransformations::chain($dataTypes)
        );
    }
}
