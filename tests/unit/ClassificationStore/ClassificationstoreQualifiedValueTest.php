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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Countrymultiselect;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Languagemultiselect;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Multiselect;
use Pimcore\Model\DataObject\ClassDefinition\Data\Table;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\MultiValue as MultiValueEntry;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Tool;

/**
 * Both classification store targets over a store whose engine holds variants and lists of values:
 * a JSON array in one source field, one declared variant per mapping entry, the refusal of a
 * variant by the batch target, and the two spellings of a key reference.
 */
class ClassificationstoreQualifiedValueTest extends Unit
{
    private const STORE = 'diQualStore';

    private const GROUP = 'diQualGroup';

    private const GROUP_IDENTIFIER = 'di-qual-group';

    private const CLASS_NAME = 'DiCsQualClass';

    private const KEY_LIST = 'diQualList';

    private const KEY_LIST_VARIANT = 'diQualListVariant';

    private const KEY_VARIANT = 'diQualVariant';

    private const KEY_PLAIN = 'diQualPlain';

    private const KEY_PLAIN_IDENTIFIER = 'di-qual-plain';

    private const VARIANT_METRIC = 'metric';

    private const VARIANT_IMPERIAL = 'imperial';

    private const OTHER_LANGUAGE = 'default';

    private const ARRAY_TYPES = [
        'multiselect' => [Multiselect::class, ['one', 'two']],
        'countrymultiselect' => [Countrymultiselect::class, ['AT', 'DE']],
        'languagemultiselect' => [Languagemultiselect::class, ['en', 'de']],
        'table' => [Table::class, [['a', 'b']]],
    ];

    private int $groupId;

    private string $language;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, 'document');
        $group = $fixture->group($store->getId(), self::GROUP, self::GROUP_IDENTIFIER);

        $list = new MultiValue();
        $list->setName(self::KEY_LIST);
        $list->setDelegateDatatype('input');
        $list->setMultiValue(true);

        // a key that declares both a list and a variant: core refuses an undeclared variant
        // outright, so the isolation of one variant from another needs a key that declares one
        $listVariant = new MultiValue();
        $listVariant->setName(self::KEY_LIST_VARIANT);
        $listVariant->setDelegateDatatype('input');
        $listVariant->setMultiValue(true);
        $listVariant->setVariants([self::VARIANT_METRIC]);

        $variant = new MultiValue();
        $variant->setName(self::KEY_VARIANT);
        $variant->setDelegateDatatype('input');
        $variant->setVariants([self::VARIANT_METRIC, self::VARIANT_IMPERIAL]);

        $keys = [
            self::KEY_LIST => ['multiValue', $list, null],
            self::KEY_LIST_VARIANT => ['multiValue', $listVariant, null],
            self::KEY_VARIANT => ['multiValue', $variant, null],
            self::KEY_PLAIN => ['input', null, self::KEY_PLAIN_IDENTIFIER],
        ];
        foreach (self::ARRAY_TYPES as $type => [$definitionClass]) {
            /** @var Data $definition */
            $definition = new $definitionClass();
            $definition->setName('diQual_' . $type);
            $keys['diQual_' . $type] = [$type, $definition, null];
        }

        $sorter = 1;
        foreach ($keys as $name => [$type, $definition, $identifier]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition, $identifier);
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

    public function testAJsonArrayReplacesTheWholeListOfTheAddressedSlotThroughBothTargets(): void
    {
        foreach (['single', 'batch'] as $targetKind) {
            $element = $this->newElement();
            $write = fn (mixed $value) => $this->write($targetKind, $element, self::KEY_LIST, $value);

            $write(json_encode(['a', 'b', 'c']));
            static::assertSame(['a', 'b', 'c'], $this->list($element, self::KEY_LIST, $this->language), $targetKind);

            $write(json_encode(['x', 'y']));
            static::assertSame(
                ['x', 'y'],
                $this->list($element, self::KEY_LIST, $this->language),
                $targetKind . ': a list can shorten'
            );

            $element->save();
            $reloaded = $this->reload($element);
            static::assertSame(['x', 'y'], $this->list($reloaded, self::KEY_LIST, $this->language), $targetKind);
        }
    }

    public function testAListLeavesOtherLanguagesAlone(): void
    {
        $field = ClassificationStoreFixture::FIELD_LOCALIZED;
        $keyId = $this->keyIds[self::KEY_LIST];
        $element = $this->newElement();
        $store = $this->store($element, $field);
        $store->setKeyValues($this->groupId, $keyId, [
            new MultiValueEntry('other-language', self::OTHER_LANGUAGE),
        ]);

        $this->write('single', $element, self::KEY_LIST, json_encode(['a', 'b', 'c']), $field);
        $this->write('batch', $element, self::KEY_LIST, json_encode(['a', 'b']), $field);

        static::assertSame(['a', 'b'], $store->getKeyValues($this->groupId, $keyId, $this->language));
        static::assertSame(
            ['other-language'],
            $store->getKeyValues($this->groupId, $keyId, self::OTHER_LANGUAGE)
        );
    }

    /**
     * On the single-key target only: the batch target refuses a variant declaring key by name, so a
     * key that declares both a list and a variant is unreachable through a batch entry.
     */
    public function testAListLeavesOtherVariantsAlone(): void
    {
        $field = ClassificationStoreFixture::FIELD_LOCALIZED;
        $keyId = $this->keyIds[self::KEY_LIST_VARIANT];
        $element = $this->newElement();
        $store = $this->store($element, $field);
        $store->setKeyValues($this->groupId, $keyId, [
            new MultiValueEntry('other-variant', $this->language, self::VARIANT_METRIC),
        ]);

        $this->write('single', $element, self::KEY_LIST_VARIANT, json_encode(['a', 'b']), $field);

        static::assertSame(['a', 'b'], $store->getKeyValues($this->groupId, $keyId, $this->language));
        static::assertSame(
            ['other-variant'],
            $store->getKeyValues($this->groupId, $keyId, $this->language, self::VARIANT_METRIC)
        );
    }

    /**
     * Pins the meaning an array value had before a key could declare several values: it is the one value.
     */
    public function testAnArrayValueOnAKeyWithoutAListStaysTheSingleValue(): void
    {
        foreach (self::ARRAY_TYPES as $type => [, $probe]) {
            foreach (['single', 'batch'] as $targetKind) {
                $element = $this->newElement();
                $this->write($targetKind, $element, 'diQual_' . $type, $probe);
                $element->save();

                // read in the language it was written in: getLocalizedKeyValue() defaults to the
                // `default` slot, which a localized write never reaches
                $value = $this->store($this->reload($element))
                    ->getLocalizedKeyValue($this->groupId, $this->keyIds['diQual_' . $type], $this->language);
                static::assertEquals($probe, $value, $type . ' through ' . $targetKind);
            }
        }
    }

    public function testAMetricAndAnImperialValueOfOneKeyEachKeepTheirOwnVariant(): void
    {
        $element = $this->newElement();
        $this->write('single', $element, self::KEY_VARIANT, '2.5', null, '#' . self::VARIANT_METRIC);
        $this->write('single', $element, self::KEY_VARIANT, '1', null, '#' . self::VARIANT_IMPERIAL);
        $element->save();

        $store = $this->store($this->reload($element));
        $keyId = $this->keyIds[self::KEY_VARIANT];
        static::assertSame(
            ['2.5'],
            $store->getKeyValues($this->groupId, $keyId, $this->language, self::VARIANT_METRIC)
        );
        static::assertSame(
            ['1'],
            $store->getKeyValues($this->groupId, $keyId, $this->language, self::VARIANT_IMPERIAL)
        );
    }

    /**
     * @dataProvider batchTokensOfAVariantKey
     */
    public function testTheBatchTargetRefusesAVariantDeclaringKeyByName(string $suffix): void
    {
        $element = $this->newElement();
        $token = $this->groupId . '-' . $this->keyIds[self::KEY_VARIANT] . $suffix;

        try {
            $this->batchTarget()->assignData($element, [$token => 'value']);
            static::fail('the batch target wrote a variant declaring key');
        } catch (InvalidInputException $exception) {
            static::assertStringContainsString($token, $exception->getMessage());
            static::assertStringContainsString('single-key', $exception->getMessage());
            static::assertStringNotContainsString('Key not format', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function batchTokensOfAVariantKey(): array
    {
        return ['without a variant' => [''], 'with a variant' => ['#' . self::VARIANT_METRIC]];
    }

    /**
     * @dataProvider referenceSpellings
     */
    public function testBothTargetsResolveAKeyGivenAsIdentifiersAndAsTheIntegerPair(bool $identifiers): void
    {
        $reference = $identifiers
            ? self::GROUP_IDENTIFIER . ':' . self::KEY_PLAIN_IDENTIFIER
            : $this->groupId . '-' . $this->keyIds[self::KEY_PLAIN];

        foreach (['single', 'batch'] as $targetKind) {
            $element = $this->newElement();
            $this->write($targetKind, $element, self::KEY_PLAIN, 'stored', null, '', $reference);

            static::assertSame(
                'stored',
                $this->store($element)
                    ->getLocalizedKeyValue($this->groupId, $this->keyIds[self::KEY_PLAIN], $this->language),
                $targetKind . ' with ' . $reference
            );
        }
    }

    /**
     * @return array<string, array{bool}>
     */
    public function referenceSpellings(): array
    {
        return ['identifiers, one of them with a hyphen' => [true], 'the integer pair' => [false]];
    }

    private function newElement(): Concrete
    {
        return (new ClassificationStoreFixture())->newObject(self::CLASS_NAME, uniqid('cs-qual-', false));
    }

    private function reload(Concrete $element): Concrete
    {
        $reloaded = Concrete::getById((int)$element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        return $reloaded;
    }

    private function store(
        Concrete $element,
        string $field = ClassificationStoreFixture::FIELD_LOCALIZED
    ): ClassificationstoreModel {
        $getter = 'get' . ucfirst($field);
        $store = $element->$getter();
        static::assertInstanceOf(ClassificationstoreModel::class, $store);

        return $store;
    }

    /**
     * @return array<int, mixed>
     */
    private function list(Concrete $element, string $keyName, string $language): array
    {
        return array_values($this->store($element)
            ->getKeyValues($this->groupId, $this->keyIds[$keyName], $language));
    }

    private function batchTarget(string $field = ClassificationStoreFixture::FIELD_LOCALIZED): ClassificationstoreBatch
    {
        $target = new ClassificationstoreBatch(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings([
            'fieldName' => $field,
            'language' => $field === ClassificationStoreFixture::FIELD_LOCALIZED ? $this->language : null,
        ]);

        return $target;
    }

    /**
     * Writes one value of one key through the named target; a localized field is written in the
     * language of the test, the non-localized one in the default.
     */
    private function write(
        string $targetKind,
        Concrete $element,
        string $keyName,
        mixed $value,
        ?string $field = null,
        string $suffix = '',
        ?string $reference = null
    ): void {
        $field ??= ClassificationStoreFixture::FIELD_LOCALIZED;
        $reference ??= $this->groupId . '-' . $this->keyIds[$keyName];
        $language = $field === ClassificationStoreFixture::FIELD_LOCALIZED ? $this->language : null;

        if ($targetKind === 'batch') {
            $this->batchTarget($field)->assignData($element, [$reference . $suffix => $value]);

            return;
        }

        $target = new Classificationstore(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings(['fieldName' => $field, 'keyId' => $reference . $suffix, 'language' => $language]);
        $target->assignData($element, $value);
    }
}
