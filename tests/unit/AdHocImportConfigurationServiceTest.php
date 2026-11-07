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
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportConfigurationService;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Pimcore\Model\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdHocImportConfigurationServiceTest extends Unit
{
    private const string FOLDER = '/Products/Imported';

    private const string LOCKED_FOLDER = '/Products/Locked';

    private const string CLASS_ID = 'PROD';

    private const string NAME_COLUMN = 'Name DE';

    private User $user;

    protected function _before(): void
    {
        $this->user = new User();
        $this->user->setId(7);
    }

    public function testOnlyDataObjectImportConfigurationsTheUserMayReadAreListedByName(): void
    {
        $listed = $this->service([
            $this->configuration('products-b', $this->config()),
            $this->configuration('hidden', $this->config(), readable: false),
            $this->configuration('api', $this->config(), type: 'graphql'),
            $this->configuration('Products-a', $this->config()),
        ])->listConfigurations()->getConfigurations();

        $this->assertSame(['Products-a', 'products-b'], array_column($listed, 'name'));
    }

    public function testAConfigurationToMapWithGivesWhatTheListSaysAndItsMapping(): void
    {
        $mapping = [['label' => 'Name', 'dataSourceIndex' => ['Name'], 'dataTarget' => ['type' => 'direct']]];

        $loaded = $this->service([
            $this->configuration('other', $this->config(classId: 'CAR')),
            $this->configuration('products', $this->config(mapping: $mapping)),
        ])->loadConfiguration('products');

        $this->assertSame($mapping, $loaded['mapping']);
        $this->assertSame('products', $loaded['configuration']['name']);
        $this->assertSame(self::CLASS_ID, $loaded['configuration']['classId']);
        $this->assertSame('sku', $loaded['configuration']['matchField']);
        $this->assertSame('SKU', $loaded['configuration']['matchColumn']);
        $this->assertSame(self::FOLDER, $loaded['configuration']['newObjectsFolder']);
    }

    /**
     * @dataProvider configurationsNotToMapWith
     */
    public function testAConfigurationTheUserMayNotReadOrThatIsNoDataObjectImportIsNotFound(
        string $name,
        bool $readable,
        string $type
    ): void {
        $this->expectException(NotFoundHttpException::class);

        $this->service([$this->configuration('products', $this->config(), $readable, $type)])
            ->loadConfiguration($name);
    }

    public static function configurationsNotToMapWith(): array
    {
        return [
            'unknown name' => ['others', true, 'dataImporterDataObject'],
            'not readable' => ['products', false, 'dataImporterDataObject'],
            'another adapter' => ['products', true, 'graphql'],
        ];
    }

    public function testAConfigurationAnAdHocImportCannotUseIsRefusedWithWhy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('folder-not-allowed');

        $this->service([
            $this->configuration('locked', $this->config(location: [
                'type' => 'staticPath',
                'settings' => ['path' => self::LOCKED_FOLDER],
            ])),
        ])->loadConfiguration('locked');
    }

    public function testReadingIsAskedForTheCurrentUser(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getType')->willReturn('dataImporterDataObject');
        $configuration->expects($this->once())->method('isAllowed')->with('read', $this->user)->willReturn(false);

        $this->assertSame([], $this->service([$configuration])->listConfigurations()->getConfigurations());
    }

    public function testWithoutAPimcoreUserNothingIsListed(): void
    {
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($this->createStub(UserInterface::class));

        $this->expectException(EnvironmentException::class);

        $this->service([], $security)->listConfigurations();
    }

    public function testAConfigurationMatchingByAFieldGivesItsClassMatchAndFolder(): void
    {
        [$configuration] = $this->service([$this->configuration('products', $this->config(
            loading: ['type' => 'attribute', 'settings' => [
                'dataSourceIndex' => self::NAME_COLUMN,
                'attributeName' => 'name',
                'language' => 'de',
            ]],
            mapping: [['dataSourceIndex' => [self::NAME_COLUMN]], ['dataSourceIndex' => ['Price']]],
            interpreter: ['type' => 'csv', 'settings' => ['skipFirstRow' => true, 'saveHeaderName' => true]]
        ))])->listConfigurations()->getConfigurations();

        $this->assertSame([
            'name' => 'products',
            'classId' => self::CLASS_ID,
            'className' => 'Product',
            'matchBy' => 'field',
            'matchField' => 'name',
            'matchLanguage' => 'de',
            'matchColumn' => self::NAME_COLUMN,
            'newObjectsFolder' => self::FOLDER,
            'columns' => [self::NAME_COLUMN, 'Price'],
            'columnsBy' => 'name',
            'unusable' => null,
        ], $configuration);
    }

    public function testAConfigurationMatchingByTheObjectIdNamesNoField(): void
    {
        [$configuration] = $this->service([$this->configuration('products', $this->config(
            loading: ['type' => 'id', 'settings' => ['dataSourceIndex' => 0, 'attributeName' => 'sku']]
        ))])->listConfigurations()->getConfigurations();

        $this->assertSame('id', $configuration['matchBy']);
        $this->assertNull($configuration['matchField']);
        $this->assertNull($configuration['matchLanguage']);
        $this->assertSame('0', $configuration['matchColumn']);
        $this->assertNull($configuration['unusable']);
    }

    public function testTheColumnsAreThoseItsMatchAndMappingReadOnceEach(): void
    {
        [$configuration] = $this->service([$this->configuration('products', $this->config(
            mapping: [
                ['dataSourceIndex' => ['Name', 'Name']],
                ['dataSourceIndex' => [3, '']],
                ['dataSourceIndex' => 'Price'],
                ['dataSourceIndex' => null],
                ['label' => 'no column'],
                ['dataSourceIndex' => ['SKU']],
                'not a row',
            ]
        ))])->listConfigurations()->getConfigurations();

        $this->assertSame(['SKU', 'Name', '3', 'Price'], $configuration['columns']);
    }

    /**
     * @dataProvider unusableConfigurations
     *
     * @param array<string, mixed> $resolver
     */
    public function testWhyAnAdHocImportCannotUseAConfiguration(array $resolver, string $reason): void
    {
        [$configuration] = $this->service([$this->configuration('products', $this->config(...$resolver))])
            ->listConfigurations()->getConfigurations();

        $this->assertSame($reason, $configuration['unusable']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function unusableConfigurations(): array
    {
        $byPath = ['type' => 'path', 'settings' => ['dataSourceIndex' => 'Path']];

        return [
            'a class the user may not create' => [['classId' => 'CAR'], 'class'],
            'no class' => [['classId' => ''], 'class'],
            'matching by path' => [['loading' => $byPath], 'matching'],
            'matching nothing' => [['loading' => ['type' => 'notLoad']], 'matching'],
            'a field without its name' => [
                ['loading' => ['type' => 'attribute', 'settings' => ['dataSourceIndex' => 'SKU']]],
                'matching',
            ],
            'a field only within a tree path' => [
                ['loading' => ['type' => 'attribute', 'settings' => [
                    'dataSourceIndex' => 'SKU', 'attributeName' => 'sku', 'path' => '/Products/ShopA',
                ]]],
                'matching',
            ],
            'matching without a column' => [
                ['loading' => ['type' => 'id', 'settings' => ['dataSourceIndex' => null]]],
                'matching',
            ],
            'new objects placed by the file' => [
                ['location' => ['type' => 'findParent', 'settings' => ['dataSourceIndex' => 'Parent']]],
                'folder',
            ],
            'no new objects' => [['location' => ['type' => 'doNotCreate']], 'folder'],
            'a static path without a path' => [['location' => ['type' => 'staticPath', 'settings' => []]], 'folder'],
            'a folder that does not exist' => [
                ['location' => ['type' => 'staticPath', 'settings' => ['path' => '/Gone']]],
                'folder-missing',
            ],
            'a folder the user may not create in' => [
                ['location' => ['type' => 'staticPath', 'settings' => ['path' => self::LOCKED_FOLDER]]],
                'folder-not-allowed',
            ],
        ];
    }

    /**
     * @dataProvider wholeTreePaths
     */
    public function testAFieldMatchOverTheWholeTreeStaysUsable(string $path): void
    {
        $loading = ['type' => 'attribute', 'settings' => [
            'dataSourceIndex' => 'SKU', 'attributeName' => 'sku', 'path' => $path,
        ]];
        [$configuration] = $this->service([$this->configuration('products', $this->config(loading: $loading))])
            ->listConfigurations()->getConfigurations();

        $this->assertNull($configuration['unusable']);
        $this->assertSame('sku', $configuration['matchField']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wholeTreePaths(): array
    {
        return ['no path' => [''], 'the root' => ['/'], 'the root with blanks' => [' / ']];
    }

    /**
     * @dataProvider columnKeys
     *
     * @param array<string, mixed> $interpreter
     */
    public function testItSaysWhetherItsColumnsAreNamedByTheHeaderRowOrByPosition(
        array $interpreter,
        string $columnsBy
    ): void {
        [$configuration] = $this->service([$this->configuration('products', $this->config(interpreter: $interpreter))])
            ->listConfigurations()->getConfigurations();

        $this->assertSame($columnsBy, $configuration['columnsBy']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function columnKeys(): array
    {
        return [
            'a CSV keeping the header names' => [
                ['type' => 'csv', 'settings' => ['skipFirstRow' => true, 'saveHeaderName' => true]],
                'name',
            ],
            'a CSV skipping the header row only' => [
                ['type' => 'csv', 'settings' => ['skipFirstRow' => true, 'saveHeaderName' => false]],
                'position',
            ],
            'a CSV naming nothing without skipping the header row' => [
                ['type' => 'csv', 'settings' => ['saveHeaderName' => '1']],
                'position',
            ],
            'a CSV without settings' => [['type' => 'csv'], 'position'],
            'an XLSX with a header row' => [['type' => 'xlsx', 'settings' => ['skipFirstRow' => true]], 'position'],
            'a JSON source' => [['type' => 'json', 'settings' => []], 'name'],
        ];
    }

    public function testAConfigurationWithoutAResolverIsListedAsUnusable(): void
    {
        [$configuration] = $this->service([$this->configuration('empty', ['resolverConfig' => 'broken'])])
            ->listConfigurations()->getConfigurations();

        $this->assertSame([
            'name' => 'empty',
            'classId' => null,
            'className' => null,
            'matchBy' => null,
            'matchField' => null,
            'matchLanguage' => null,
            'matchColumn' => null,
            'newObjectsFolder' => null,
            'columns' => [],
            'columnsBy' => 'name',
            'unusable' => 'class',
        ], $configuration);
    }

    /**
     * @param array<string, mixed>|null $loading
     * @param array<string, mixed>|null $location
     * @param array<mixed> $mapping
     * @param array<string, mixed>|null $interpreter
     *
     * @return array<string, mixed>
     */
    private function config(
        string $classId = self::CLASS_ID,
        ?array $loading = null,
        ?array $location = null,
        array $mapping = [],
        ?array $interpreter = null,
    ): array {
        return [
            'interpreterConfig' => $interpreter ?? ['type' => 'csv', 'settings' => []],
            'resolverConfig' => [
                'elementType' => 'dataObject',
                'dataObjectClassId' => $classId,
                'loadingStrategy' => $loading
                    ?? ['type' => 'attribute', 'settings' => ['dataSourceIndex' => 'SKU', 'attributeName' => 'sku']],
                'createLocationStrategy' => $location
                    ?? ['type' => 'staticPath', 'settings' => ['path' => self::FOLDER]],
            ],
            'mappingConfig' => $mapping,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function configuration(
        string $name,
        array $data,
        bool $readable = true,
        string $type = 'dataImporterDataObject',
    ): Configuration {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getName')->willReturn($name);
        $configuration->method('getType')->willReturn($type);
        $configuration->method('getConfiguration')->willReturn($data);
        $configuration->method('isAllowed')->willReturnCallback(
            fn (string $permission, ?User $user = null): bool => $readable
                && $permission === 'read'
                && $user === $this->user
        );

        return $configuration;
    }

    /**
     * @param list<Configuration> $configurations
     */
    private function service(
        array $configurations,
        ?SecurityServiceInterface $security = null
    ): AdHocImportConfigurationService {
        if ($security === null) {
            $security = $this->createStub(SecurityServiceInterface::class);
            $security->method('getCurrentUser')->willReturn($this->user);
        }

        $classes = $this->createStub(ClassDefinitionServiceInterface::class);
        $classes->method('getClassDefinitionCollection')->willReturnCallback(
            static fn (bool $creatableOnly = false): array => $creatableOnly ? [new ClassDefinitionList(
                self::CLASS_ID,
                'Product',
                'Product',
                new ElementIcon(ElementIconTypes::NAME->value, 'class')
            )] : []
        );

        $class = new ClassDefinition();
        $class->setName('Product');
        $classResolver = $this->createStub(ClassDefinitionResolverInterface::class);
        $classResolver->method('getById')->willReturnCallback(
            static fn (string $id): ?ClassDefinition => in_array($id, [self::CLASS_ID, 'CAR'], true) ? $class : null
        );

        $open = $this->createStub(Folder::class);
        $open->method('isAllowed')->willReturnCallback(
            fn (string $type, ?User $user = null): bool => $type === 'create' && $user === $this->user
        );
        $locked = $this->createStub(Folder::class);
        $locked->method('isAllowed')->willReturn(false);
        $objects = $this->createStub(DataObjectResolverInterface::class);
        $objects->method('getByPath')->willReturnCallback(
            static fn (string $path): ?Folder => [self::FOLDER => $open, self::LOCKED_FOLDER => $locked][$path] ?? null
        );

        return new AdHocImportConfigurationService(
            $security,
            $classes,
            $classResolver,
            $objects,
            static fn (): array => $configurations,
        );
    }
}
