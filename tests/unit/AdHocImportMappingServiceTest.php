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
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;

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

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class)
            ->loadMapping(new MappingSuggestionParameters('PROD', [['index' => 'sku', 'label' => 'SKU']]));

        $this->assertSame([
            [...$this->target('sku'), 'mandatory' => true],
            [...$this->target('name', localized: true), 'mandatory' => true],
            [...$this->target('price', 'numeric'), 'mandatory' => false],
            [...$this->target('notes'), 'mandatory' => false],
        ], $mapping->getFields());
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
        ?ClassDefinition $class = null
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

        return new AdHocImportMappingService($classes, $suggestions, $classResolver);
    }
}
