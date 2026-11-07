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
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\Objectbrick\DefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Objectbrick\Definition;

class AdHocImportMappingServiceTest extends Unit
{
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
        $class->addFieldDefinition('sku', $this->input('sku', mandatory: true));
        $class->addFieldDefinition('price', $this->input('price', mandatory: false));
        $localized = new Localizedfields();
        $localized->setName('localizedfields');
        $localized->addChild($this->input('name', mandatory: true));
        $class->addFieldDefinition('localizedfields', $localized);

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class, fieldTypes: [
            TransformationDataTypeService::DEFAULT_TYPE => ['sku', 'name', 'notes'],
            TransformationDataTypeService::NUMERIC => ['price'],
        ])->loadMapping(new MappingSuggestionParameters('PROD', [['index' => 'sku', 'label' => 'SKU']]));

        $this->assertSame([
            [...$this->target('sku'), 'mandatory' => true, 'takesColumnAsIs' => true],
            [...$this->target('name', localized: true), 'mandatory' => true, 'takesColumnAsIs' => true],
            [...$this->target('price', 'numeric'), 'mandatory' => false, 'takesColumnAsIs' => true],
            [...$this->target('notes'), 'mandatory' => false, 'takesColumnAsIs' => true],
        ], $mapping->getFields());
    }

    public function testTheMappingSaysWhichFieldsTakeAColumnWithoutATransformation(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([
            $this->target('price'),
            $this->target('image', 'asset'),
            $this->target('weight', 'quantityValue'),
        ]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, fieldTypes: [
            TransformationDataTypeService::NUMERIC => ['price'],
            TransformationDataTypeService::ASSET => ['image'],
        ])->loadMapping(new MappingSuggestionParameters('PROD', []));

        // a number is converted; an image needs its asset loaded; a quantity value needs a unit
        $this->assertSame(
            ['price' => true, 'image' => false, 'weight' => false],
            array_column($mapping->getFields(), 'takesColumnAsIs', 'key')
        );
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
        $parameters = new MappingSuggestionParameters('PROD', []);
        $suggestions = $this->createMock(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target($key), $this->target('name')]);
        // left out before matching, so that a column it would have won gets its next best field
        $suggestions->expects($this->once())->method('suggest')
            ->with($parameters, $this->callback(static fn (array $excluded): bool => in_array($key, $excluded, true)))
            ->willReturn(new MappingSuggestionsResponse([$this->suggestion('Name', 'name')]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions)->loadMapping($parameters);

        $this->assertSame(['name'], array_column($mapping->getFields(), 'key'));
        $this->assertSame(['name'], array_column($mapping->getSuggestions(), 'targetFieldName'));
    }

    public function testABrickFieldTheBrickRequiresIsRequired(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([
            $this->target('attributes.Dimensions.width'),
            $this->target('attributes.Dimensions.depth'),
            $this->target('attributes.Other.width'),
        ]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        // setAllowedTypes() would load the brick definitions from disk
        $bricks = $this->createStub(Objectbricks::class);
        $bricks->method('getName')->willReturn('attributes');
        $bricks->method('getAllowedTypes')->willReturn(['Dimensions']);
        $class = new ClassDefinition();
        $class->addFieldDefinition('attributes', $bricks);

        $dimensions = new Definition();
        $dimensions->addFieldDefinition('width', $this->input('width', mandatory: true));
        $dimensions->addFieldDefinition('depth', $this->input('depth', mandatory: false));
        $other = new Definition();
        $other->addFieldDefinition('width', $this->input('width', mandatory: true));

        $mapping = $this->service(
            creatableClasses: ['PROD'],
            suggestions: $suggestions,
            class: $class,
            bricks: ['Dimensions' => $dimensions, 'Other' => $other]
        )->loadMapping(new MappingSuggestionParameters('PROD', []));

        // a brick the container does not allow writes nothing, so nothing of it is required
        $this->assertSame(
            ['attributes.Dimensions.width' => true, 'attributes.Dimensions.depth' => false, 'attributes.Other.width' => false],
            array_column($mapping->getFields(), 'mandatory', 'key')
        );
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
     * @param array<string, Definition> $bricks
     * @param array<string, list<string>> $fieldTypes per result type the fields that take it
     */
    private function service(
        array $creatableClasses,
        MappingSuggestionServiceInterface $suggestions,
        ?ClassDefinition $class = null,
        array $bricks = [],
        array $fieldTypes = []
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

        $brickResolver = $this->createStub(DefinitionResolverInterface::class);
        $brickResolver->method('getByKey')->willReturnCallback(static fn (string $key): ?Definition => $bricks[$key] ?? null);

        $types = $this->createStub(DataTypeServiceInterface::class);
        $types->method('loadClassAttributes')->willReturnCallback(
            static fn (string $classId, string $type): ClassAttributesResponse => new ClassAttributesResponse(array_map(
                static fn (string $key): array => ['key' => $key, 'title' => $key, 'localized' => false],
                $fieldTypes[$type] ?? []
            ))
        );

        return new AdHocImportMappingService(
            $classes,
            $suggestions,
            $classResolver,
            $brickResolver,
            AdHocTransformations::chain($types)
        );
    }
}
