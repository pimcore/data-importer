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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition\Data\Date;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Tool;

/**
 * Both classification store targets over a store on the EAV engine, which holds neither variants
 * nor lists of values, and a batch entry carrying a key it cannot build.
 */
class ClassificationstoreUnstorableValueTest extends Unit
{
    private const STORE = 'diEavStore';

    private const GROUP = 'diEavGroup';

    private const GROUP_IDENTIFIER = 'di-eav-group';

    private const CLASS_NAME = 'DiCsEavClass';

    private const KEY_INPUT = 'diEavInput';

    private const KEY_DATE = 'diEavDate';

    private const KEY_VARIANT = 'diEavVariant';

    private const KEY_LIST = 'diEavList';

    private const KEY_BULK = 'diEavBulk';

    private const BULK_SIZE = 50;

    private const VARIANT = 'metric';

    private int $groupId;

    private string $language;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE);
        $group = $fixture->group($store->getId(), self::GROUP, self::GROUP_IDENTIFIER);

        $variant = new MultiValue();
        $variant->setName(self::KEY_VARIANT);
        $variant->setDelegateDatatype('input');
        $variant->setVariants([self::VARIANT]);

        $list = new MultiValue();
        $list->setName(self::KEY_LIST);
        $list->setDelegateDatatype('input');
        $list->setMultiValue(true);

        $keys = [
            self::KEY_INPUT => ['input', null],
            self::KEY_DATE => ['date', new Date()],
            self::KEY_VARIANT => ['multiValue', $variant],
            self::KEY_LIST => ['multiValue', $list],
        ];
        for ($i = 0; $i < self::BULK_SIZE; $i++) {
            $keys[self::KEY_BULK . $i] = ['input', null];
        }

        $sorter = 1;
        foreach ($keys as $name => [$type, $definition]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition, $this->identifierOf($name));
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->groupId = $group->getId();
        $this->language = (string)(Tool::getValidLanguages()[0] ?? 'en');
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testAKeyTheEntryCannotBuildIsWarnedAboutAndSkippedWhileTheRestOfTheRowIsWritten(): void
    {
        $dateToken = $this->groupId . '-' . $this->keyIds[self::KEY_DATE];
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(static fn (string $message): bool => str_contains($message, $dateToken)
                && str_contains($message, 'date')
                && str_contains($message, 'Date')),
            static::anything()
        );

        $element = $this->newElement();
        $target = new ClassificationstoreBatch(
            $logger,
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_LOCALIZED,
            'language' => $this->language,
        ]);
        $target->assignData($element, [
            $this->groupId . '-' . $this->keyIds[self::KEY_INPUT] => 'kept',
            $dateToken => '2021-07-22',
        ]);
        $element->save();

        $store = $this->store($this->reload($element));
        static::assertSame('kept', $this->valueOf($store, self::KEY_INPUT));
        static::assertNull($this->valueOf($store, self::KEY_DATE));
    }

    public function testAVariantOnAStoreWithoutVariantsWarnsAndWritesTheDefaultSlot(): void
    {
        $token = $this->groupId . '-' . $this->keyIds[self::KEY_VARIANT] . '#' . self::VARIANT;
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(static fn (string $message): bool => str_contains($message, $token)
                && str_contains($message, self::VARIANT)
                && str_contains($message, 'engine')),
            static::anything()
        );

        $element = $this->newElement();
        $this->singleKeyTarget($logger, self::KEY_INPUT)->assignData($element, 'untouched');
        $this->singleKeyTarget($logger, self::KEY_VARIANT, '#' . self::VARIANT)->assignData($element, 'qualified');
        $element->save();

        $store = $this->store($this->reload($element));
        static::assertSame('qualified', $this->valueOf($store, self::KEY_VARIANT));
        static::assertSame('untouched', $this->valueOf($store, self::KEY_INPUT));
    }

    public function testAListOnAStoreWithoutListsWarnsAndWritesTheFirstValueAsTheDefaultSlot(): void
    {
        $token = (string)$this->groupId . '-' . $this->keyIds[self::KEY_LIST];
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(
                static fn (string $message): bool => str_contains($message, $token)
                    && str_contains($message, 'list of 2')
            ),
            static::anything()
        );

        $element = $this->newElement();
        $this->singleKeyTarget($logger, self::KEY_LIST)->assignData($element, json_encode(['first', 'second']));
        $element->save();

        $store = $this->store($this->reload($element));
        static::assertSame('first', $this->valueOf($store, self::KEY_LIST));
    }

    /**
     * The statement-count bound on identifier resolution: resolving a reference costs no lookup of
     * its own, because `ClassificationstoreKeyTrait::preloadIdentifierReferences()` reads every
     * pending reference of a row together.
     *
     * Counts the statements one import row costs through the batch target, for one key and for
     * fifty, both spelled as identifiers, and bounds the marginal cost of the forty-nine further
     * references at less than one statement each. A lookup per reference costs at least one, so it
     * fails here; the preload adds none, so the two counts come out equal. The measured pair is
     * written to the run's output either way.
     */
    public function testResolvingIdentifiersCostsNoStatementPerReference(): void
    {
        $one = $this->statementsFor(1);
        $many = $this->statementsFor(self::BULK_SIZE);
        $further = self::BULK_SIZE - 1;

        codecept_debug(sprintf('statements for 1 key: %d, for %d keys: %d', $one, self::BULK_SIZE, $many));
        static::assertLessThan(
            $further,
            $many - $one,
            sprintf(
                '%d further references cost %d further statements, which is at least one each: '
                . 'identifier resolution is looking a reference up instead of preloading it',
                $further,
                $many - $one
            )
        );
    }

    private function statementsFor(int $keys): int
    {
        $tokens = [];
        for ($i = 0; $i < $keys; $i++) {
            $tokens[self::GROUP_IDENTIFIER . ':' . $this->identifierOf(self::KEY_BULK . $i)] = 'value ' . $i;
        }

        DefinitionCache::clear(null);
        $element = $this->newElement();
        $target = new ClassificationstoreBatch(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_LOCALIZED,
            'language' => $this->language,
        ]);

        $before = $this->questions();
        $target->assignData($element, $tokens);

        // the second reading is itself one statement
        return max(1, $this->questions() - $before - 1);
    }

    private function questions(): int
    {
        $row = Db::get()->fetchAssociative("SHOW SESSION STATUS LIKE 'Questions'");

        return (int)($row['Value'] ?? 0);
    }

    private function identifierOf(string $keyName): string
    {
        return 'di-eav-' . strtolower($keyName);
    }

    private function newElement(): Concrete
    {
        return (new ClassificationStoreFixture())->newObject(self::CLASS_NAME, uniqid('cs-eav-', false));
    }

    private function reload(Concrete $element): Concrete
    {
        $reloaded = Concrete::getById((int)$element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        return $reloaded;
    }

    private function store(Concrete $element): ClassificationstoreModel
    {
        $store = $element->getCsLocalizedField();
        static::assertInstanceOf(ClassificationstoreModel::class, $store);

        return $store;
    }

    /**
     * The value the store holds for a key of the group, in the language of the test.
     */
    private function valueOf(ClassificationstoreModel $store, string $keyName): mixed
    {
        return $store->getLocalizedKeyValue($this->groupId, $this->keyIds[$keyName], $this->language);
    }

    private function singleKeyTarget(
        ApplicationLogger $logger,
        string $keyName,
        string $suffix = ''
    ): Classificationstore {
        $target = new Classificationstore(
            $logger,
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_LOCALIZED,
            'language' => $this->language,
            'keyId' => $this->groupId . '-' . $this->keyIds[$keyName] . $suffix,
        ]);

        return $target;
    }
}
