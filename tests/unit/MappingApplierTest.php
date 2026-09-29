<?php
declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\MappingApplicationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLookupInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceType;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Tool\SettingsStore;
use Pimcore\Tests\Support\Util\TestHelper;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MappingApplierTest extends Unit
{
    private const CLASS_NAME = 'ApplyMappingTarget';

    private const DATA_HUB_SCOPE = 'pimcore_data_hub';

    private const NEW_TITLE = 'new title';

    private const ASSET_FOLDER = '/apply-mapping-assets';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private ClassDefinition $class;

    private ?string $tempFile = null;

    // unique per test, Data Hub caches configurations for the process
    private ?string $configName = null;

    protected function _before(): void
    {
        $this->class = ClassDefinition::getByName(self::CLASS_NAME) ?? $this->createClass();
    }

    protected function _after(): void
    {
        if ($this->configName !== null) {
            SettingsStore::delete($this->configName, self::DATA_HUB_SCOPE);
        }
        if ($this->tempFile !== null && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        TestHelper::cleanUp();
    }

    public function testAppliesTheMappingWithoutSaving(): void
    {
        $object = $this->createObject('existing', ['name' => 'old name']);
        $modificationDate = $object->getModificationDate();
        $versionCount = $this->countVersions($object);

        $events = $this->recordEvents([
            PreSaveEvent::class,
            PostSaveEvent::class,
            DataObjectEvents::PRE_UPDATE,
            DataObjectEvents::POST_UPDATE,
        ]);

        $copy = $this->loadDetached($object);
        $this->applier()->prepare([
            $this->directItem('name', 'name', [['type' => 'trim', 'settings' => ['mode' => 'both']]]),
            $this->directItem('title', 'title'),
        ])->apply($copy, ['name' => '  new name  ', 'title' => self::NEW_TITLE]);

        $this->assertSame('new name', $copy->get('name'));
        $this->assertSame(self::NEW_TITLE, $copy->get('title'));

        $stored = $this->loadDetached($object);
        $this->assertSame('old name', $stored->get('name'));
        $this->assertNull($stored->get('title'));
        $this->assertSame($modificationDate, $stored->getModificationDate());
        $this->assertSame($versionCount, $this->countVersions($object));
        $this->assertSame([], $events->getArrayCopy());
    }

    /**
     * Same mapping and row through the queue of a stored configuration and through the applier.
     */
    public function testMatchesTheImportForTheSameMappingAndRow(): void
    {
        $values = ['name' => 'kept name', 'description' => 'kept description'];
        $imported = $this->createObject('imported', $values);
        $applied = $this->createObject('applied', $values);

        $mapping = [
            $this->directItem('name', 'name', [], ['writeIfTargetIsNotEmpty' => false]),
            $this->directItem('description', 'description', [], ['writeIfSourceIsEmpty' => false]),
            $this->directItem('title', 'title'),
        ];
        $row = ['name' => 'new name', 'description' => '', 'title' => self::NEW_TITLE];

        $this->import($mapping, $imported, $row);

        $copy = $this->loadDetached($applied);
        $this->applier()->prepare($mapping)->apply($copy, $row);

        $imported = $this->loadDetached($imported);
        $expectedValues = ['name' => 'kept name', 'description' => 'kept description', 'title' => self::NEW_TITLE];
        foreach ($expectedValues as $field => $expected) {
            $this->assertSame($expected, $imported->get($field), 'import: ' . $field);
            $this->assertSame($imported->get($field), $copy->get($field), 'applied: ' . $field);
        }
    }

    public function testReferenceLookupReplacesTheOperatorsOwnLookup(): void
    {
        $existing = $this->createObject('referenced');
        $placeholder = $this->newObject('placeholder');
        $placeholder->setId(-1);
        $lookup = $this->lookup(fn (): ?ElementInterface => $placeholder);

        $copy = $this->newObject('target');
        $row = ['ref' => ' ' . $existing->getFullPath() . ' '];
        $this->applier()->prepare([$this->loadDataObjectItem()])->apply($copy, $row, $lookup);

        $this->assertSame($placeholder, $copy->get('related'));
        $this->assertCount(1, $lookup->queries);
        $query = $lookup->queries[0];
        $this->assertSame(ReferenceType::DataObject, $query->type);
        $this->assertSame(ReferenceLoadStrategy::Path, $query->loadStrategy);
        $this->assertSame($existing->getFullPath(), $query->key);
    }

    public function testOperatorsOwnLookupIsUsedWithoutHookOrWhenTheHookFindsNothing(): void
    {
        $existing = $this->createObject('referenced');
        $row = ['ref' => $existing->getFullPath()];
        $prepared = $this->applier()->prepare([$this->loadDataObjectItem()]);

        $withoutHook = $this->newObject('without-hook');
        $prepared->apply($withoutHook, $row);
        $this->assertSame($existing->getId(), $withoutHook->get('related')?->getId());

        $lookup = $this->lookup(fn (): ?ElementInterface => null);
        $hookFindsNothing = $this->newObject('hook-finds-nothing');
        $prepared->apply($hookFindsNothing, $row, $lookup);
        $this->assertSame($existing->getId(), $hookFindsNothing->get('related')?->getId());
        $this->assertCount(1, $lookup->queries);
    }

    public function testReferenceLookupResolvesAssets(): void
    {
        $placeholder = new Asset\Image();
        $placeholder->setId(-2);
        $lookup = $this->lookup(fn (): ?ElementInterface => $placeholder);

        $copy = $this->newObject('target');
        $this->applier()->prepare([[
            'label' => 'file',
            'dataSourceIndex' => ['file'],
            'transformationPipeline' => [['type' => 'loadAsset', 'settings' => ['loadStrategy' => 'id']]],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'file']],
        ]])->apply($copy, ['file' => '123'], $lookup);

        $this->assertSame($placeholder, $copy->get('file'));
        $this->assertSame(ReferenceType::Asset, $lookup->queries[0]->type);
        $this->assertSame(ReferenceLoadStrategy::Id, $lookup->queries[0]->loadStrategy);
        $this->assertSame('123', $lookup->queries[0]->key);
    }

    public function testReferenceLookupIsClearedAfterARunThatThrows(): void
    {
        $existing = $this->createObject('referenced');
        $failing = $this->lookup(function (): ?ElementInterface {
            throw new \UnexpectedValueException('lookup failed');
        });
        $prepared = $this->applier()->prepare([$this->loadDataObjectItem()]);

        try {
            $prepared->apply($this->newObject('target'), ['ref' => $existing->getFullPath()], $failing);
            $this->fail('The lookup exception was swallowed.');
        } catch (MappingApplicationException $exception) {
            $this->assertSame(0, $exception->getItemIndex());
            $this->assertSame('related', $exception->getItemLabel());
            $this->assertStringContainsString('lookup failed', $exception->getMessage());
            $this->assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
        }

        $scope = $this->tester->grabService(MappingApplicationScope::class);
        $this->assertFalse($scope->isActive());
        $this->assertNull($scope->getReferenceLookup());

        $afterwards = $this->newObject('afterwards');
        $prepared->apply($afterwards, ['ref' => $existing->getFullPath()]);
        $this->assertSame($existing->getId(), $afterwards->get('related')?->getId());
        $this->assertCount(1, $failing->queries);
    }

    public function testFailureNamesTheMappingItem(): void
    {
        $prepared = $this->applier()->prepare([
            $this->directItem('name', 'name'),
            $this->directItem('missing', 'doesNotExist', [], ['writeIfTargetIsNotEmpty' => false], 'Broken item'),
        ]);

        $this->expectException(MappingApplicationException::class);
        $this->expectExceptionMessage('Mapping item 1 (`Broken item`) failed');

        $prepared->apply($this->newObject('target'), ['name' => 'a', 'missing' => 'b']);
    }

    public function testWritingOperatorIsListedAndRefused(): void
    {
        $mapping = [
            $this->directItem('name', 'name'),
            $this->importAssetItem(),
            [
                'label' => 'loaded file',
                'dataSourceIndex' => ['file'],
                'transformationPipeline' => [['type' => 'loadAsset', 'settings' => ['loadStrategy' => 'path']]],
                'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'file']],
            ],
        ];

        $issues = $this->applier()->lint($mapping);
        $this->assertCount(1, $issues);
        $this->assertSame(1, $issues[0]->itemIndex);
        $this->assertSame('file', $issues[0]->itemLabel);
        $this->assertStringContainsString('`importAsset`', $issues[0]->message);

        $this->assertSame([], $this->applier()->lint([$this->directItem('name', 'name')]));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('importAsset');
        $this->applier()->prepare($mapping);
    }

    public function testLintListsInvalidItems(): void
    {
        $issues = $this->applier()->lint([
            [
                'label' => 'unknown',
                'dataSourceIndex' => ['a'],
                'transformationPipeline' => [['type' => 'doesNotExist']],
                'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'name']],
            ],
            ['label' => 'no target', 'dataSourceIndex' => ['a']],
        ]);

        $this->assertCount(2, $issues);
        $this->assertStringContainsString('doesNotExist', $issues[0]->message);
        $this->assertSame('no target', $issues[1]->itemLabel);
    }

    public function testImportWithImportAssetStillSavesTheAsset(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/apply-mapping-' . uniqid() . '.txt';
        file_put_contents($this->tempFile, 'asset content');
        $object = $this->createObject('with-asset');

        $this->import([$this->importAssetItem()], $object, ['file' => $this->tempFile]);

        $asset = $this->loadDetached($object)->get('file');
        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertGreaterThan(0, $asset->getId());
        $this->assertSame(self::ASSET_FOLDER, $asset->getParent()?->getFullPath());
        $this->assertSame('asset content', Asset::getById($asset->getId(), ['force' => true])?->getData());
    }

    private function applier(): MappingApplier
    {
        return $this->tester->grabService(MappingApplier::class);
    }

    private function import(array $mapping, Concrete $object, array $row): void
    {
        $this->configName = uniqid('apply-mapping-test-');
        // seeded like Data Hub stores it; saving a Configuration needs the Data Hub workspace tables
        $configuration = [
            'general' => [
                'active' => true,
                'type' => 'dataImporterDataObject',
                'name' => $this->configName,
                'path' => '',
            ],
            'resolverConfig' => [
                'elementType' => 'dataObject',
                'dataObjectClassId' => $this->class->getId(),
                'loadingStrategy' => ['type' => 'id', 'settings' => ['dataSourceIndex' => 'id']],
                'createLocationStrategy' => ['type' => 'doNotCreate'],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
            'processingConfig' => [],
            'mappingConfig' => $mapping,
        ];
        SettingsStore::set(
            $this->configName,
            json_encode($configuration, JSON_THROW_ON_ERROR),
            SettingsStore::TYPE_STRING,
            self::DATA_HUB_SCOPE
        );

        /** @var QueueService $queueService */
        $queueService = $this->tester->grabService(QueueService::class);
        $queueService->addItemToQueue(
            $this->configName,
            ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
            ImportProcessingService::JOB_TYPE_PROCESS,
            json_encode(['id' => $object->getId()] + $row, JSON_THROW_ON_ERROR)
        );
        $entryIds = $queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $this->assertCount(1, $entryIds);

        // the test database has no application log table
        $errors = [];
        $applicationLogger = $this->createMock(ApplicationLogger::class);
        $applicationLogger->method('error')->willReturnCallback(static function ($message) use (&$errors): void {
            $errors[] = (string) $message;
        });
        $processingService = new ImportProcessingService(
            $queueService,
            $this->tester->grabService(MappingConfigurationFactory::class),
            $this->tester->grabService(ResolverFactory::class),
            $this->tester->grabService(CleanupStrategyFactory::class),
            $applicationLogger,
            $this->tester->grabService('event_dispatcher'),
        );
        $processingService->setLogger(new NullLogger());

        $this->withoutSearchIndexUpdates(fn () => $processingService->processQueueItem((int) $entryIds[0]));
        $this->assertSame([], $errors, 'import errors');
    }

    /**
     * Updating a data object makes the search index look up its siblings, and the test environment has no search
     * index to answer.
     */
    private function withoutSearchIndexUpdates(callable $callback): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->tester->grabService('event_dispatcher');
        $detached = [];
        foreach ($dispatcher->getListeners(DataObjectEvents::POST_UPDATE) as $listener) {
            $owner = is_array($listener) ? $listener[0] : $listener;
            if (is_object($owner) && str_starts_with($owner::class, 'Pimcore\\Bundle\\GenericDataIndexBundle\\')) {
                $detached[] = [$listener, $dispatcher->getListenerPriority(DataObjectEvents::POST_UPDATE, $listener)];
                $dispatcher->removeListener(DataObjectEvents::POST_UPDATE, $listener);
            }
        }

        try {
            $callback();
        } finally {
            foreach ($detached as [$listener, $priority]) {
                $dispatcher->addListener(DataObjectEvents::POST_UPDATE, $listener, $priority ?? 0);
            }
        }
    }

    private function directItem(
        string $source,
        string $field,
        array $pipeline = [],
        array $targetSettings = [],
        ?string $label = null
    ): array
    {
        return [
            'label' => $label ?? $field,
            'dataSourceIndex' => [$source],
            'transformationPipeline' => $pipeline,
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => $field] + $targetSettings],
        ];
    }

    private function loadDataObjectItem(): array
    {
        return [
            'label' => 'related',
            'dataSourceIndex' => ['ref'],
            'transformationPipeline' => [['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']]],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'related']],
        ];
    }

    private function importAssetItem(): array
    {
        return [
            'label' => 'file',
            'dataSourceIndex' => ['file'],
            'transformationPipeline' => [
                ['type' => 'importAsset', 'settings' => ['parentFolder' => self::ASSET_FOLDER]],
            ],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'file']],
        ];
    }

    /**
     * @param callable(ReferenceQuery): ?ElementInterface $find
     */
    private function lookup(callable $find): ReferenceLookupInterface
    {
        return new class($find) implements ReferenceLookupInterface {
            /** @var list<ReferenceQuery> */
            public array $queries = [];

            /** @var callable(ReferenceQuery): ?ElementInterface */
            private $find;

            public function __construct(callable $find)
            {
                $this->find = $find;
            }

            public function find(ReferenceQuery $query): ?ElementInterface
            {
                $this->queries[] = $query;

                return ($this->find)($query);
            }
        };
    }

    /**
     * @param list<string> $eventNames
     */
    private function recordEvents(array $eventNames): \ArrayObject
    {
        $recorded = new \ArrayObject();
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->tester->grabService('event_dispatcher');
        foreach ($eventNames as $eventName) {
            $dispatcher->addListener($eventName, static function () use ($recorded, $eventName): void {
                $recorded[] = $eventName;
            });
        }

        return $recorded;
    }

    private function countVersions(Concrete $object): int
    {
        return (int) \Pimcore\Db::get()->fetchOne(
            'SELECT COUNT(*) FROM versions WHERE ctype = ? AND cid = ?',
            ['object', $object->getId()]
        );
    }

    private function loadDetached(Concrete $object): Concrete
    {
        $loaded = DataObject::getById($object->getId(), ['force' => true]);
        $this->assertInstanceOf(Concrete::class, $loaded);

        return $loaded;
    }

    private function newObject(string $key): Concrete
    {
        $className = '\\Pimcore\\Model\\DataObject\\' . self::CLASS_NAME;
        /** @var Concrete $object */
        $object = new $className();
        $object->setKey($key . '-' . uniqid());
        $object->setParentId(1);
        $object->setPublished(true);

        return $object;
    }

    private function createObject(string $key, array $values = []): Concrete
    {
        $object = $this->newObject($key);
        foreach ($values as $field => $value) {
            $object->set($field, $value);
        }
        $object->save();

        return $object;
    }

    private function createClass(): ClassDefinition
    {
        $fields = [];
        foreach (['name', 'description', 'title'] as $name) {
            $input = new ClassDefinition\Data\Input();
            $input->setName($name);
            $input->setTitle($name);
            $fields[] = $input;
        }

        $related = new ClassDefinition\Data\ManyToOneRelation();
        $related->setName('related');
        $related->setTitle('related');
        $related->setObjectsAllowed(true);
        $fields[] = $related;

        $file = new ClassDefinition\Data\ManyToOneRelation();
        $file->setName('file');
        $file->setTitle('file');
        $file->setAssetsAllowed(true);
        $fields[] = $file;

        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $panel->setChildren($fields);

        $class = new ClassDefinition();
        $class->setName(self::CLASS_NAME);
        $class->setLayoutDefinitions($panel);
        $class->save();

        return $class;
    }
}
