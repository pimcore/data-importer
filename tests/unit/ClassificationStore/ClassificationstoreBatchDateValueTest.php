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

use Carbon\Carbon;
use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Date as DateDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Datetime as DatetimeDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input as InputDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue as MultiValueDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric as NumericDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * A `date` or `datetime` key inside a mixed `array` batch entry, whose normalized form is a unix
 * timestamp rather than the array the per-key build used to offer alone - which is why those two
 * types were never attempted and a date key was warned about and skipped whatever it carried.
 *
 * The timestamp is now read and a formatted string is not, and the second half is the row that
 * matters: `Date::denormalize()` hands what it is given to `Carbon::setTimestamp()`, which reads
 * `2026-01-01` as the integer 2026 and would store 1970 with no error at all.
 */
class ClassificationstoreBatchDateValueTest extends Unit
{
    private const STORE = 'diDateStore';

    private const ENGINE_DOCUMENT = 'document';

    private const GROUP = 'diDateGroup';

    private const CLASS_NAME = 'DiCsDateClass';

    private const KEY_DATE = 'diDateDate';

    private const KEY_DATETIME = 'diDateDatetime';

    private const KEY_INPUT = 'diDateInput';

    private const KEY_NUMERIC = 'diDateNumeric';

    private const KEY_WRAPPED_DATE = 'diDateWrappedDate';

    private const TYPE_DATE = 'date';

    /**
     * The shape `Date::normalize()` emits for 2026-01-01 00:00:00 UTC, which is therefore the shape
     * its `denormalize()` reads.
     */
    private const TIMESTAMP_DATE = 1767225600;

    private const EXPECTED_DATE = '2026-01-01 00:00:00';

    private const TIMESTAMP_DATETIME = 1772615700;

    private const EXPECTED_DATETIME = '2026-03-04 09:15:00';

    /**
     * A formatted date, which carries no format for the target to read it with.
     */
    private const FORMATTED_DATE = '2026-01-01';

    private const EPOCH_YEAR = '1970';

    private int $groupId;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        // a store saved rather than created writes no engine column, so it is flipped explicitly
        $fixture->flipEngine($store, self::ENGINE_DOCUMENT);
        $group = $fixture->group($store->getId(), self::GROUP);

        $sorter = 1;
        foreach ($this->keyDefinitions() as $name => [$type, $definition]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition);
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->groupId = $group->getId();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    /**
     * The normalized form of a date, given as the integer it is: the key holds the point in time that
     * timestamp means, asserted in UTC so that the server's own zone cannot answer for it.
     *
     * @dataProvider timestampValues
     */
    public function testADateKeyBuildsThePointInTimeItsTimestampMeans(
        string $keyName,
        int|string $timestamp,
        int $expectedTimestamp,
        string $expectedUtc
    ): void {
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::never())->method('warning');

        $element = $this->write([$this->token($keyName) => $timestamp], $logger);

        $value = $this->keyValue($element, $keyName);
        static::assertInstanceOf(Carbon::class, $value, 'the timestamp was not built into a point in time');
        static::assertSame(
            $expectedUtc,
            $value->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'the key holds a different point in time than the timestamp means'
        );
        static::assertSame($expectedTimestamp, $value->getTimestamp());
    }

    /**
     * Both date types, each given its timestamp as an integer and as the numeric string a CSV or a
     * JSON source carries it as.
     *
     * @return array<string, array{string, int|string, int, string}>
     */
    public function timestampValues(): array
    {
        return [
            'date, integer' => [self::KEY_DATE, self::TIMESTAMP_DATE, self::TIMESTAMP_DATE, self::EXPECTED_DATE],
            'date, numeric string' => [
                self::KEY_DATE,
                (string) self::TIMESTAMP_DATE,
                self::TIMESTAMP_DATE,
                self::EXPECTED_DATE,
            ],
            'datetime, integer' => [
                self::KEY_DATETIME,
                self::TIMESTAMP_DATETIME,
                self::TIMESTAMP_DATETIME,
                self::EXPECTED_DATETIME,
            ],
            'datetime, numeric string' => [
                self::KEY_DATETIME,
                (string) self::TIMESTAMP_DATETIME,
                self::TIMESTAMP_DATETIME,
                self::EXPECTED_DATETIME,
            ],
        ];
    }

    /**
     * The regression guard of the whole change: a formatted date string stays refused. Widening the
     * build to every scalar would pass `2026-01-01` to `Carbon::setTimestamp()`, which reads it as
     * the integer 2026 and stores 1970 with no error, so the key is asserted to hold nothing at all
     * rather than merely to differ.
     *
     * @dataProvider dateKeys
     */
    public function testAFormattedDateStringIsStillWarnedAboutAndSkipped(string $keyName, string $keyType): void
    {
        $token = $this->token($keyName);
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(static fn (string $message): bool => str_contains($message, $token)
                && str_contains($message, $keyType)
                && str_contains($message, 'Date')),
            static::anything()
        );

        $element = $this->write([$token => self::FORMATTED_DATE], $logger);

        $value = $this->keyValue($element, $keyName);
        static::assertNotInstanceOf(
            Carbon::class,
            $value,
            'the formatted string was read as a timestamp; it would mean ' . self::EPOCH_YEAR
        );
        static::assertNull($value, 'the formatted date string was written to the key');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function dateKeys(): array
    {
        return [
            'date' => [self::KEY_DATE, 'date'],
            'datetime' => [self::KEY_DATETIME, 'datetime'],
        ];
    }

    /**
     * The control: a key whose type is no point in time keeps the scalar it was given, so that the
     * numeric test of the date branch cannot reach a type whose scalar already IS its stored value.
     *
     * @dataProvider scalarKeys
     */
    public function testANonDateScalarKeyKeepsTheValueItWasGiven(string $keyName, mixed $value): void
    {
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::never())->method('warning');

        $element = $this->write([$this->token($keyName) => $value], $logger);

        static::assertSame($value, $this->keyValue($element, $keyName), $keyName . ' was rebuilt');
    }

    /**
     * Both a numeric key and an `input` key carrying digits: the second is the one a dispatch on the
     * raw value rather than on the key's definition would have turned into a date.
     *
     * @return array<string, array{string, mixed}>
     */
    public function scalarKeys(): array
    {
        return [
            'numeric' => [self::KEY_NUMERIC, 42],
            'input holding digits' => [self::KEY_INPUT, (string) self::TIMESTAMP_DATE],
            'input holding a formatted date' => [self::KEY_INPUT, self::FORMATTED_DATE],
        ];
    }

    /**
     * One entry carrying a built point in time next to a kept scalar, so that the dispatch is shown
     * to act per key rather than per entry, and none of them is warned about.
     */
    public function testOneEntryCarriesADateAndAPlainScalarSideBySide(): void
    {
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::never())->method('warning');

        $element = $this->write([
            $this->token(self::KEY_DATE) => self::TIMESTAMP_DATE,
            $this->token(self::KEY_DATETIME) => (string) self::TIMESTAMP_DATETIME,
            $this->token(self::KEY_INPUT) => 'plain text',
        ], $logger);

        static::assertSame(self::TIMESTAMP_DATE, $this->keyValue($element, self::KEY_DATE)?->getTimestamp());
        static::assertSame(self::TIMESTAMP_DATETIME, $this->keyValue($element, self::KEY_DATETIME)?->getTimestamp());
        static::assertSame('plain text', $this->keyValue($element, self::KEY_INPUT));
    }

    /**
     * A key wrapped in `multiValue` is dispatched on what it holds, not on its own type column, which
     * reads `multiValue` whatever the delegate is - the same reason the quantity value branch reads
     * the value definition. A dispatch on the key's own type would not recognise this key as a date
     * at all and would leave it to be warned about and skipped.
     */
    public function testAWrappedDateKeyIsDispatchedOnItsDelegate(): void
    {
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::never())->method('warning');

        $element = $this->write([$this->token(self::KEY_WRAPPED_DATE) => self::TIMESTAMP_DATE], $logger);

        $value = $this->keyValue($element, self::KEY_WRAPPED_DATE);
        static::assertInstanceOf(Carbon::class, $value, 'the wrapped key was not dispatched on its delegate');
        static::assertSame(
            self::EXPECTED_DATE,
            $value->copy()->setTimezone('UTC')->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, array{string, Data|null}> key name => [key type, field definition]
     */
    private function keyDefinitions(): array
    {
        return [
            self::KEY_DATE => [self::TYPE_DATE, $this->named(new DateDefinition(), self::KEY_DATE)],
            self::KEY_DATETIME => ['datetime', $this->named(new DatetimeDefinition(), self::KEY_DATETIME)],
            self::KEY_INPUT => ['input', $this->named(new InputDefinition(), self::KEY_INPUT)],
            self::KEY_NUMERIC => ['numeric', $this->named(new NumericDefinition(), self::KEY_NUMERIC)],
            self::KEY_WRAPPED_DATE => ['multiValue', $this->wrappedDate(self::KEY_WRAPPED_DATE)],
        ];
    }

    /**
     * A `multiValue` definition over a `date` delegate. The delegate object is set as well as its type
     * name, because `Service::getFieldDefinitionFromJson()` rebuilds the inner definition from the
     * blob the wrapper carries, and a wrapper naming a type but carrying nothing reads back with no
     * delegate at all.
     */
    private function wrappedDate(string $name): MultiValueDefinition
    {
        $definition = new MultiValueDefinition();
        $definition->setName($name);
        $definition->setDelegateDatatype(self::TYPE_DATE);
        $definition->setDelegate($this->named(new DateDefinition(), $name));

        return $definition;
    }

    private function named(Data $definition, string $name): Data
    {
        $definition->setName($name);

        return $definition;
    }

    /**
     * One entry through the batch target, against a fresh object.
     *
     * @param array<string, mixed> $entry
     */
    private function write(array $entry, ?ApplicationLogger $logger = null): Concrete
    {
        $fixture = new ClassificationStoreFixture();
        $element = $fixture->object(self::CLASS_NAME, uniqid('cs-date-', false));

        $target = new ClassificationstoreBatch(
            $logger ?? $this->createMock(ApplicationLogger::class),
            $fixture->localizationResolver(),
            $fixture->engineSupport()
        );
        $target->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);
        $target->assignData($element, $entry);

        return $element;
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
