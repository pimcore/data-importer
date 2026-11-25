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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data\Geopoint as GeopointDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * A batch entry carrying a key whose type the engine behind its store cannot hold. The engine is
 * asked before the value is built, and the answer is a warning and a skipped key: one unholdable
 * key does not cost the row every other key of the entry.
 *
 * The key is declared while the store is on an engine that holds it and the store is moved
 * afterwards, which is the only way such a key comes to exist - `KeyConfig::save()` refuses to
 * introduce one.
 */
class ClassificationstoreBatchEngineSupportTest extends Unit
{
    private const STORE = 'diEngineStore';

    private const ENGINE_DOCUMENT = 'document';

    private const ENGINE_EAV = 'eav';

    private const GROUP = 'diEngineGroup';

    private const CLASS_NAME = 'DiCsEngineClass';

    private const KEY_POINT = 'diEnginePoint';

    private const KEY_INPUT = 'diEngineInput';

    /**
     * The shape `Geopoint::normalize()` emits, so that the key is skipped for the engine's sake and
     * not because its value could not be built.
     */
    private const POINT = ['latitude' => 47.8, 'longitude' => 13.0];

    private int $groupId;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        $fixture->flipEngine($store, self::ENGINE_DOCUMENT);
        $group = $fixture->group($store->getId(), self::GROUP);

        $point = new GeopointDefinition();
        $point->setName(self::KEY_POINT);

        $keys = [
            self::KEY_POINT => ['geopoint', $point],
            self::KEY_INPUT => ['input', null],
        ];

        $sorter = 1;
        foreach ($keys as $name => [$type, $definition]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition);
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->flipEngine($store, self::ENGINE_EAV);
        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->groupId = $group->getId();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testAnUnholdableKeyIsWarnedAboutAndSkippedWhileTheRestOfTheEntryIsWritten(): void
    {
        $pointToken = $this->token(self::KEY_POINT);
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(static fn (string $message): bool => str_contains($message, $pointToken)
                && str_contains($message, 'geopoint')
                && str_contains($message, self::ENGINE_EAV)),
            static::anything()
        );

        $fixture = new ClassificationStoreFixture();
        $element = $fixture->object(self::CLASS_NAME, uniqid('cs-engine-', false));

        $target = new ClassificationstoreBatch(
            $logger,
            $fixture->localizationResolver(),
            $fixture->engineSupport()
        );
        $target->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);
        $target->assignData($element, [
            $pointToken => self::POINT,
            $this->token(self::KEY_INPUT) => 'kept',
        ]);

        static::assertNull($this->keyValue($element, self::KEY_POINT), 'the unholdable key was written');
        static::assertSame('kept', $this->keyValue($element, self::KEY_INPUT), 'the rest of the entry was lost');
    }

    private function token(string $keyName): string
    {
        return $this->groupId . '-' . $this->keyIds[$keyName];
    }

    private function keyValue(Concrete $element, string $keyName): mixed
    {
        $getter = 'get' . ucfirst(ClassificationStoreFixture::FIELD_NON_LOCALIZED);
        $classificationStore = $element->$getter();
        static::assertInstanceOf(ClassificationstoreModel::class, $classificationStore);

        return $classificationStore->getLocalizedKeyValue($this->groupId, $this->keyIds[$keyName]);
    }
}
