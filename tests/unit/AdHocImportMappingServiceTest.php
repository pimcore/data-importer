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
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Fieldset;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Tabpanel;

class AdHocImportMappingServiceTest extends Unit
{
    private const BASE_DATA = 'Base data';

    public function testTheMappingOffersTheClassFieldsSayingWhichTheClassRequires(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturnMap([['PROD', [
            $this->target('sku'),
            $this->target('name', localized: true),
            $this->target('price', 'numeric'),
            $this->target('notes'),
        ]]]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        $class = new ClassDefinition();
        $class->setName('Product');
        $class->addFieldDefinition('sku', $this->input('sku', mandatory: true));
        $class->addFieldDefinition('price', $this->input('price', mandatory: false));
        $localized = new Localizedfields();
        $localized->setName('localizedfields');
        $localized->addChild($this->input('name', mandatory: true));
        $class->addFieldDefinition('localizedfields', $localized);

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class)
            ->loadMapping(new MappingSuggestionParameters('PROD', [['index' => 'sku', 'label' => 'SKU']]));

        // a class without a layout groups every field by its name
        $grouped = ['group' => ['Product'], 'classificationStore' => false];
        $this->assertSame([
            [...$this->target('sku'), 'mandatory' => true, ...$grouped],
            [...$this->target('name', localized: true), 'mandatory' => true, ...$grouped],
            [...$this->target('price', 'numeric'), 'mandatory' => false, ...$grouped],
            [...$this->target('notes'), 'mandatory' => false, ...$grouped],
        ], $mapping->getFields());
    }

    public function testTheFieldsAreGroupedByTheClassLayoutAsInTheGrid(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([
            $this->target('sku'),
            $this->target('name', localized: true),
            $this->target('price', 'numeric'),
            $this->target('notes'),
            $this->target('technical.Engine.power', 'numeric'),
            $this->target('unknown'),
        ]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        $localized = new Localizedfields();
        $localized->setName('localizedfields');
        $localized->addChild($this->input('name', mandatory: false));
        $bricks = new Objectbricks();
        $bricks->setName('technical');
        $class = $this->classWithLayout($this->layout(Panel::class, '', [
            $this->layout(Tabpanel::class, '', [
                $this->layout(Panel::class, self::BASE_DATA, [
                    $this->input('sku', mandatory: false),
                    // a titled layout below a titled one does not group again
                    $this->layout(Fieldset::class, 'Texts', [$localized]),
                ]),
                $this->layout(Panel::class, 'Technical', [$bricks, $this->input('price', mandatory: false)]),
            ]),
            $this->input('notes', mandatory: false),
        ]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));

        $this->assertSame([
            'sku' => [self::BASE_DATA],
            'name' => [self::BASE_DATA],
            'price' => ['Technical'],
            'notes' => ['Product'],
            'technical.Engine.power' => ['Technical', 'technical', 'Engine'],
            'unknown' => ['Product'],
        ], array_column($mapping->getFields(), 'group', 'key'));
    }

    public function testAClassificationStoreIsOfferedAsAFieldWhoseKeyIsChosenApart(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target('sku')]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        $store = new Classificationstore();
        $store->setName('attributes');
        $store->setTitle('Attributes');
        $store->setLocalized(true);
        $class = $this->classWithLayout($this->layout(Panel::class, '', [
            $this->input('sku', mandatory: false),
            $this->layout(Panel::class, 'Specification', [$store]),
        ]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));

        $this->assertSame([
            'key' => 'attributes',
            'title' => 'Attributes [attributes]',
            'localized' => true,
            'transformationResultType' => 'default',
            'mandatory' => false,
            'group' => ['Specification'],
            'classificationStore' => true,
        ], $mapping->getFields()[1]);
        $this->assertFalse($mapping->getFields()[0]['classificationStore']);
    }

    public function testTheMappingSuggestsForTheColumnsAsked(): void
    {
        $column = 'Bezeichnung DE';
        $parameters = new MappingSuggestionParameters('PROD', [['index' => $column, 'label' => $column]]);
        $suggestion = [
            'sourceIndex' => $column,
            'sourceLabel' => $column,
            'targetFieldName' => 'bezeichnung',
            'targetFieldLabel' => 'Bezeichnung',
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => 'de',
        ];
        $suggestions = $this->createMock(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target('bezeichnung', localized: true)]);
        $suggestions->expects($this->once())->method('suggest')->with($parameters)
            ->willReturn(new MappingSuggestionsResponse([$suggestion]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions)->loadMapping($parameters);

        $this->assertSame([$suggestion], $mapping->getSuggestions());
        // without the class definition nothing counts as required
        $this->assertFalse($mapping->getFields()[0]['mandatory']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldsAnAdHocImportDoesNotWrite(): iterable
    {
        yield 'the key, which new objects take from their match value' => ['key'];
        yield 'the path, as objects stay where they are found' => ['path'];
        yield 'the object type of a class with variants' => ['type'];
    }

    /**
     * @dataProvider fieldsAnAdHocImportDoesNotWrite
     */
    public function testTheMappingNeitherOffersNorSuggestsAField(string $key): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target($key), $this->target('name')]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([
            $this->suggestion('Pfad', $key),
            $this->suggestion('Name', 'name'),
        ]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));

        $this->assertSame(['name'], array_column($mapping->getFields(), 'key'));
        $this->assertSame(['name'], array_column($mapping->getSuggestions(), 'targetFieldName'));
    }

    public function testTheMappingOfAClassTheUserMayNotCreateIsForbidden(): void
    {
        $suggestions = $this->createMock(MappingSuggestionServiceInterface::class);
        $suggestions->expects($this->never())->method('loadTargets');
        $suggestions->expects($this->never())->method('suggest');

        $this->expectException(ForbiddenException::class);

        $this->service(creatableClasses: ['OTHER'], suggestions: $suggestions)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));
    }

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

        return $this->classWithLayout($this->layout(Panel::class, '', [$this->input('sku', mandatory: false), $store]));
    }

    private function classWithLayout(ClassDefinition\Layout $layout): ClassDefinition
    {
        $class = new ClassDefinition();
        $class->setName('Product');
        $class->setLayoutDefinitions($layout);

        return $class;
    }

    /**
     * @param class-string<ClassDefinition\Layout> $type
     * @param list<ClassDefinition\Layout|ClassDefinition\Data> $children
     */
    private function layout(string $type, string $title, array $children): ClassDefinition\Layout
    {
        $layout = new $type();
        $layout->setName($title === '' ? 'untitled' : $title);
        $layout->setTitle($title);
        $layout->setChildren($children);

        return $layout;
    }

    /**
     * @return array{key: string, title: string, localized: bool, transformationResultType: string}
     */
    private function target(string $key, string $type = 'default', bool $localized = false): array
    {
        return ['key' => $key, 'title' => $key, 'localized' => $localized, 'transformationResultType' => $type];
    }

    /**
     * @return array{
     *     sourceIndex: string,
     *     sourceLabel: string,
     *     targetFieldName: string,
     *     targetFieldLabel: string,
     *     transformationResultType: string,
     *     score: int,
     *     language: null
     * }
     */
    private function suggestion(string $column, string $field): array
    {
        return [
            'sourceIndex' => $column,
            'sourceLabel' => $column,
            'targetFieldName' => $field,
            'targetFieldLabel' => $field,
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => null,
        ];
    }

    private function input(string $name, bool $mandatory): Input
    {
        $input = new Input();
        $input->setName($name);
        $input->setMandatory($mandatory);

        return $input;
    }

    /**
     * @param string[] $creatableClasses
     */
    private function service(
        array $creatableClasses,
        MappingSuggestionServiceInterface $suggestions,
        ?ClassDefinition $class = null,
        ?DataTypeServiceInterface $dataTypes = null
    ): AdHocImportMappingService {
        $classes = $this->createStub(ClassDefinitionServiceInterface::class);
        $classes->method('getClassDefinitionCollection')->willReturnCallback(
            static fn (bool $creatableOnly = false): array => $creatableOnly ? array_map(
                static fn (string $id): ClassDefinitionList => new ClassDefinitionList(
                    $id,
                    $id,
                    $id,
                    new ElementIcon(ElementIconTypes::NAME->value, 'class')
                ),
                $creatableClasses
            ) : []
        );

        $classResolver = $this->createStub(ClassDefinitionResolverInterface::class);
        $classResolver->method('getById')->willReturn($class);

        return new AdHocImportMappingService(
            $classes,
            $suggestions,
            $classResolver,
            $dataTypes ?? $this->createStub(DataTypeServiceInterface::class)
        );
    }
}
