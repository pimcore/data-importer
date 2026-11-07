<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\MappingSuggestionsEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\MappingSuggestionHydrator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StaticResolverBundle\Lib\ToolResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MappingSuggestionServiceTest extends Unit
{
    /**
     * @param array<string, list<array<string, mixed>>> $attributesByType
     */
    private function service(
        array $attributesByType,
        ?EventDispatcherInterface $dispatcher = null,
        bool $classExists = true,
        array &$requests = [],
        array $rights = [PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG],
        array $creatableClasses = []
    ): MappingSuggestionService {
        $dataTypes = $this->createStub(DataTypeServiceInterface::class);
        $dataTypes->method('loadClassAttributes')->willReturnCallback(
            static function (
                string $classId,
                string|array $type,
                bool $read,
                bool $write,
                bool $advanced
            ) use ($attributesByType, &$requests): ClassAttributesResponse {
                $requests[] = [$classId, $type, $read, $write, $advanced];

                return new ClassAttributesResponse($attributesByType[is_array($type) ? $type[0] : $type] ?? []);
            }
        );

        $classes = $this->createStub(ClassDefinitionResolverInterface::class);
        $classes->method('getById')->willReturn($classExists ? new ClassDefinition() : null);

        $tool = $this->createStub(ToolResolverInterface::class);
        $tool->method('getValidLanguages')->willReturn(['de', 'en']);

        $user = new User();
        $user->setAdmin(false);
        foreach ($rights as $right) {
            $user->setPermission($right, true);
        }
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        $classService = $this->createStub(ClassDefinitionServiceInterface::class);
        $classService->method('getClassDefinitionCollection')->willReturnCallback(
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

        return new MappingSuggestionService(
            $dataTypes,
            $classes,
            $tool,
            new ColumnMatcher(),
            new MappingSuggestionHydrator(),
            $dispatcher ?? new EventDispatcher(),
            $security,
            $classService
        );
    }

    private function parameters(string ...$labels): MappingSuggestionParameters
    {
        $columns = [];
        foreach ($labels as $index => $label) {
            $columns[] = ['index' => 'col_' . $index, 'label' => $label];
        }

        return new MappingSuggestionParameters('Product', $columns);
    }

    private function attribute(string $key, bool $localized = false): array
    {
        return ['key' => $key, 'title' => $key . ' [' . $key . ']', 'localized' => $localized];
    }

    public function testItSuggestsFromTheClassAttributesWithTheLanguageGuessedFromTheColumnName(): void
    {
        $response = $this->service([
            'default' => [$this->attribute('bezeichnung', true), $this->attribute('sku')],
        ])->suggest($this->parameters('Bezeichnung DE', 'SKU', 'unrelated'));

        $this->assertSame([
            [
                'sourceIndex' => 'col_0',
                'sourceLabel' => 'Bezeichnung DE',
                'targetFieldName' => 'bezeichnung',
                'targetFieldLabel' => 'bezeichnung [bezeichnung]',
                'transformationResultType' => 'default',
                'score' => 100,
                'language' => 'de',
            ],
            [
                'sourceIndex' => 'col_1',
                'sourceLabel' => 'SKU',
                'targetFieldName' => 'sku',
                'targetFieldLabel' => 'sku [sku]',
                'transformationResultType' => 'default',
                'score' => 100,
                'language' => null,
            ],
        ], $response->getSuggestions());
    }

    public function testAnExcludedFieldIsLeftOutBeforeMatchingSoTheColumnGetsTheNextBest(): void
    {
        $attributes = ['default' => [$this->attribute('key'), ['key' => 'keyCode', 'title' => 'Key code [keyCode]']]];

        $this->assertSame(
            ['key'],
            array_column(
                $this->service($attributes)->suggest($this->parameters('Key'))->getSuggestions(),
                'targetFieldName'
            )
        );
        $this->assertSame(
            ['keyCode'],
            array_column(
                $this->service($attributes)->suggest($this->parameters('Key'), ['key'])->getSuggestions(),
                'targetFieldName'
            )
        );
    }

    public function testAFieldOfAnotherResultTypeIsSuggestedWithThatType(): void
    {
        $response = $this->service(['date' => [$this->attribute('validFrom')]])
            ->suggest($this->parameters('valid_from'));

        $this->assertSame('date', $response->getSuggestions()[0]['transformationResultType']);
    }

    public function testAFieldOfferedBySeveralResultTypesKeepsTheFirst(): void
    {
        $response = $this->service([
            'boolean' => [$this->attribute('name'), $this->attribute('image')],
            'default' => [$this->attribute('name')],
            'asset' => [$this->attribute('image')],
        ])->suggest($this->parameters('name', 'image'));

        $this->assertSame(
            ['default', 'boolean'],
            array_column($response->getSuggestions(), 'transformationResultType')
        );
    }

    public function testItAsksForTheWritableFieldsOfEveryResultTypeASuggestionCanWrite(): void
    {
        $requests = [];
        $this->service([], null, true, $requests)->suggest($this->parameters('name'));

        $this->assertSame([
            ['Product', ['default', 'numeric'], false, true, false],
            ['Product', 'array', false, true, false],
            ['Product', 'boolean', false, true, false],
            ['Product', 'date', false, true, false],
            ['Product', 'quantityValue', false, true, false],
            ['Product', 'asset', false, true, false],
            ['Product', 'assetArray', false, true, false],
            ['Product', 'gallery', false, true, false],
            ['Product', 'dataObject', false, true, false],
            ['Product', 'dataObjectArray', false, true, false],
        ], $requests);
    }

    public function testTheTargetsAreEveryFieldOnceWithTheResultTypeThatWritesIt(): void
    {
        $targets = $this->service([
            'default' => [$this->attribute('name', true)],
            'date' => [$this->attribute('validFrom'), $this->attribute('name', true)],
        ])->loadTargets('Product');

        $this->assertSame([
            ['key' => 'name', 'title' => 'name [name]', 'localized' => true, 'transformationResultType' => 'default'],
            [
                'key' => 'validFrom',
                'title' => 'validFrom [validFrom]',
                'localized' => false,
                'transformationResultType' => 'date',
            ],
        ], $targets);
    }

    public function testAnAttributeWithoutTheLocalizedFlagIsNotLocalized(): void
    {
        $response = $this->service(['default' => [['key' => 'name', 'title' => 'Name']]])
            ->suggest($this->parameters('name_de'));

        $this->assertNull($response->getSuggestions()[0]['language']);
    }

    public function testAnUnknownClassIsNotFoundAndLoadsNothing(): void
    {
        $requests = [];
        $service = $this->service([], null, false, $requests);

        try {
            $service->suggest($this->parameters('name'));
            $this->fail('expected a NotFoundException');
        } catch (NotFoundException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertSame([], $requests);
    }

    public function testThePreResponseEventCarriesTheResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = null;
        $dispatcher->addListener(
            MappingSuggestionsEvent::EVENT_NAME,
            static function (MappingSuggestionsEvent $event) use (&$seen): void {
                $seen = $event->getMappingSuggestions();
            }
        );

        $response = $this->service([], $dispatcher)->suggest($this->parameters('name'));

        $this->assertSame($response, $seen);
    }

    /**
     * One case per role: the endpoint has no route-level right, so these are its access rules.
     *
     * @dataProvider roles
     *
     * @param string[] $rights
     * @param string[] $creatableClasses
     */
    public function testWhoGetsSuggestions(array $rights, array $creatableClasses, bool $allowed): void
    {
        $requests = [];
        $service = $this->service(
            ['default' => [$this->attribute('sku')]],
            null,
            true,
            $requests,
            $rights,
            $creatableClasses
        );

        if (!$allowed) {
            try {
                $service->suggest($this->parameters('SKU'));
                $this->fail('expected a ForbiddenException');
            } catch (ForbiddenException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame([], $requests, 'a refused request reads no class field');

            return;
        }

        $suggestions = $service->suggest($this->parameters('SKU'))->getSuggestions();
        $this->assertSame([[
            'sourceIndex' => 'col_0',
            'sourceLabel' => 'SKU',
            'targetFieldName' => 'sku',
            'targetFieldLabel' => 'sku [sku]',
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => null,
        ]], $suggestions);
    }

    public function roles(): array
    {
        $config = PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;
        $adHoc = PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT;

        return [
            'neither right' => [[], ['Product'], false],
            'another data hub right only' => [[PermissionConstants::PLUGIN_DATA_IMPORTER_ADAPTER], ['Product'], false],
            'only the ad hoc right, a class it may create' => [[$adHoc], ['Product'], true],
            'only the ad hoc right, a class it may not create' => [[$adHoc], ['Category'], false],
            'only the configuration right, any class' => [[$config], [], true],
            'both rights, any class' => [[$config, $adHoc], [], true],
        ];
    }

    public function testAnAdHocUserAskingForAnUnknownClassIsNotFound(): void
    {
        $requests = [];
        $this->expectException(NotFoundException::class);

        $this->service(
            [],
            null,
            false,
            $requests,
            [PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT],
            ['Product']
        )->suggest($this->parameters('SKU'));
    }
}
