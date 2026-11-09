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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ConvertedColumn;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ColumnWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ConfigurationItemWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ImportField;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\PreparedMapping;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceVisibilityInterface;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\User;

class AdHocDryRunWritersTest extends Unit
{
    public function testARowHasErrorsWithoutAnObjectOrWithAnInvalidValue(): void
    {
        $this->assertFalse((new DryRunRow(2, 'A-1', DryRunRow::UPDATE))->hasErrors());
        $this->assertTrue((new DryRunRow(3, 'A-2', null, 'not-found'))->hasErrors());
        $this->assertTrue((new DryRunRow(4, 'A-3', DryRunRow::CREATE, invalid: [
            ['field' => 0, 'label' => 'Price', 'value' => 'abc', 'message' => 'abc is no number'],
        ]))->hasErrors());
    }

    public function testOnlyARowWithAnObjectAndNoRefusalBeyondItsValuesImportsWithoutThem(): void
    {
        $this->assertTrue((new DryRunRow(2, 'A-1', DryRunRow::UPDATE))->importsWithoutInvalidValues());
        $this->assertFalse((new DryRunRow(3, 'A-2', null, 'not-found'))->importsWithoutInvalidValues());
        $this->assertFalse((new DryRunRow(4, 'A-3', DryRunRow::UPDATE, blocked: true))->importsWithoutInvalidValues());
    }

    public function testAColumnWriterWritesTheConvertedValueIntoItsField(): void
    {
        $field = new ImportField('name', 'en', 'Name');
        $writer = new ColumnWriter($field, 1, new ConvertedColumn(['Chair'], [false], [false], null));
        $object = $this->writable();

        $this->assertSame($field, $writer->field());
        $this->assertNull($writer->write($object, 0, ['A-1', 'Chair']));
        $this->assertSame(['Chair', 'en'], $object->written);
    }

    public function testAColumnWriterKeepsAValueThatIsNoElementForEveryUser(): void
    {
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn(['Chair'], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertNull($writer->forUser(AdHocRunFixtures::user(false))->write($object, 0, ['A-1', 'Chair']));
        $this->assertSame(['Chair', null], $object->written);
    }

    public function testAColumnWriterRefusesARowWhoseValueLostAnElementItsUserMayNotView(): void
    {
        $user = AdHocRunFixtures::user(false);
        // the transformations left the element out for the user the plan was made for
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([null, 'Chair'], [false, false], [false, false], null, [true, false])
        );
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->write($object, 0, ['A-1', '7']), 'the dry run');
        $this->assertSame(ValueError::Invalid, $writer->forUser($user)->write($object, 0, ['A-1', '7']), 'the run');
        $this->assertNull($object->written, 'the field is not cleared');

        $this->assertNull($writer->forUser($user)->write($object, 1, ['A-2', 'Chair']));
        $this->assertSame(['Chair', null], $object->written);
    }

    public function testAColumnWriterForAUserRefusesASingleElementTheyMayNotView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([$this->element($user, false)], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->forUser($user)->write($object, 0, ['A-1', '1']));
        $this->assertNull($object->written, 'the field keeps what it holds');
    }

    public function testAColumnWriterForAUserRefusesAListHoldingAnElementTheyMayNotView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([[$this->element($user, false), $this->element($user, true)]], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->forUser($user)->write($object, 0, ['A-1', '1,2']));
        $this->assertNull($object->written, 'the list is not shortened');
    }

    public function testAColumnWriterForAUserRefusesAKeyedValueHoldingAnElementTheyMayNotView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $value = ['visible' => $this->element($user, true), 'hidden' => $this->element($user, false)];
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([$value], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->forUser($user)->write($object, 0, ['A-1', '1,2']));
        $this->assertNull($object->written);
    }

    public function testAColumnWriterForAUserRefusesAGalleryHoldingAnImageTheyMayNotView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $gallery = new ImageGallery([
            new Hotspotimage($this->image($user, true)),
            null,
            new Hotspotimage($this->image($user, false)),
        ]);
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([$gallery], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->forUser($user)->write($object, 0, ['A-1', '1,2']));
        $this->assertNull($object->written, 'the gallery is not shortened');
    }

    public function testAColumnWriterForAUserWritesAListOfElementsTheyMayAllView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $list = [$this->element($user, true), $this->element($user, true)];
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([$list], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertNull($writer->forUser($user)->write($object, 0, ['A-1', '1,2']));
        $this->assertSame([$list, null], $object->written);
    }

    public function testAColumnWriterForAUserWritesAGalleryOfImagesTheyMayAllView(): void
    {
        $user = AdHocRunFixtures::user(false);
        $gallery = new ImageGallery([new Hotspotimage($this->image($user, true)), null, new Hotspotimage()]);
        $writer = new ColumnWriter(
            new ImportField('name', null, 'Name'),
            1,
            new ConvertedColumn([$gallery], [false], [false], null)
        );
        $object = $this->writable();

        $this->assertNull($writer->forUser($user)->write($object, 0, ['A-1', '1,2']));
        $this->assertSame([$gallery, null], $object->written);
    }

    public function testAColumnWriterWritesAnEmptyCellAsNull(): void
    {
        $column = new ConvertedColumn([null], [false], [false], null);
        $writer = new ColumnWriter(new ImportField('name', null, 'Name'), 1, $column);
        $object = $this->writable();
        $object->written = ['Old', null];

        $this->assertNull($writer->write($object, 0, ['A-1', '']));
        $this->assertSame([null, null], $object->written);
    }

    public function testAnUnwritableColumnFailsEachValueAndClearsWithAnEmptyCell(): void
    {
        $column = ConvertedColumn::unwritable(['3 m', '', null]);

        $this->assertSame([null, null, null], $column->values);
        $this->assertSame([true, false, false], $column->failed);
        $this->assertSame([true, false, false], $column->failedInSteps);
        $this->assertNull($column->conversion);

        $writer = new ColumnWriter(new ImportField('name', null, 'Name'), 1, $column);
        $object = $this->writable();
        $this->assertSame(ValueError::Invalid, $writer->write($object, 0, []), 'a value does not fit the field');
        $this->assertNull($object->written);
        $this->assertNull($writer->write($object, 1, []), 'an empty cell clears the field');
        $this->assertSame([null, null], $object->written);
    }

    public function testAColumnWriterSaysWhyAValueCannotGoIntoItsField(): void
    {
        $converted = new ConvertedColumn(
            [null, null, null, 'x'],
            [true, false, false, false],
            [true, true, true, false],
            'numeric'
        );
        $writer = new ColumnWriter(new ImportField('name', null, 'Name'), 1, $converted);
        $object = $this->writable();

        $this->assertSame(ValueError::Invalid, $writer->write($object, 0, []), 'failed in the transformations');
        $this->assertSame(ValueError::NotANumber, $writer->write($object, 1, []), 'failed in the conversion');
        $this->assertSame(ValueError::Invalid, $writer->write($object, 9, []), 'a row the column has no value for');
        $this->assertNull($object->written, 'a failed value leaves the field as it was');

        $dates = new ConvertedColumn([null], [false], [true], 'date');
        $dateWriter = new ColumnWriter(new ImportField('name', null, 'Name'), 1, $dates);
        $this->assertSame(ValueError::NotADate, $dateWriter->write($object, 0, []));
    }

    public function testAColumnWriterSaysInvalidWhenTheFieldRefusesTheValue(): void
    {
        $column = new ConvertedColumn(['many'], [false], [false], null);
        $writer = new ColumnWriter(new ImportField('count', null, 'Count'), 1, $column);
        $object = new class() extends Concrete {
            public function setCount(int $count, ?string $language = null): static
            {
                return $this;
            }
        };

        $this->assertSame(ValueError::Invalid, $writer->write($object, 0, ['A-1', 'many']));
    }

    public function testAnErrorThatIsNoRefusedValueIsNotReportedAsAnInvalidCell(): void
    {
        $column = new ConvertedColumn(['Chair'], [false], [false], null);
        $writer = new ColumnWriter(new ImportField('name', null, 'Name'), 1, $column);
        $object = new class() extends Concrete {
            public function setName(mixed $name, ?string $language = null): static
            {
                throw new \LogicException('caught as a refused value');
            }
        };
        $this->assertSame(ValueError::Invalid, $writer->write($object, 0, ['A-1', 'Chair']));

        $broken = new class() extends Concrete {
            public function setName(mixed $name, ?string $language = null): static
            {
                throw new \Error('a bug in the setter');
            }
        };
        $this->expectException(\Error::class);
        $writer->write($broken, 0, ['A-1', 'Chair']);
    }

    public function testAClassificationStoreColumnSaysWhyAValueCannotGoIntoItsKey(): void
    {
        $field = new ImportField('specs', 'de', 'Dimensions › Width', new StoreKey(3, 7));
        $writer = new ColumnWriter($field, 'width', new ConvertedColumn([null], [false], [true], 'numeric'));

        $this->assertSame($field, $writer->field());
        $this->assertSame(ValueError::NotANumber, $writer->write($this->writable(), 0, ['width' => 'wide']));
        $this->assertSame('wide', $writer->fileValue(['width' => 'wide']));
    }

    public function testAColumnWriterGivesTheFileValueAsTheFileHasIt(): void
    {
        $writer = new ColumnWriter(new ImportField('name', null, 'Name'), 1, new ConvertedColumn([], [], [], null));

        $this->assertSame('Chair', $writer->fileValue(['A-1', 'Chair']));
        $this->assertSame('0', $writer->fileValue(['A-1', 0]));
        $this->assertNull($writer->fileValue(['A-1', '']), 'an empty cell');
        $this->assertNull($writer->fileValue(['A-1']), 'a missing cell');
        $this->assertNull($writer->fileValue(['A-1', ['nested']]), 'a value that is no text');
    }

    public function testAConfigurationItemGivesTheValuesOfItsColumnsAsTheFileHasThem(): void
    {
        $field = new ImportField(null, null, 'Price');
        $mapping = (new \ReflectionClass(PreparedMapping::class))->newInstanceWithoutConstructor();
        $references = $this->createStub(ReferenceVisibilityInterface::class);
        $writer = new ConfigurationItemWriter($field, $mapping, [1, 'currency', 3], $references);

        $this->assertSame($field, $writer->field());
        $this->assertSame('12.5 · EUR', $writer->fileValue([1 => '12.5', 'currency' => 'EUR', 3 => '']));
        $this->assertSame('7', $writer->fileValue([1 => 7, 'currency' => ['no text']]));
        $this->assertNull($writer->fileValue([1 => '', 'currency' => null]), 'every column empty');
        $this->assertNull($writer->fileValue([]), 'no column in the row');
    }

    private function element(User $user, bool $visible): Concrete
    {
        $element = $this->createStub(Concrete::class);
        $element->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $asked): bool => $visible && $type === 'view' && $asked === $user
        );

        return $element;
    }

    private function image(User $user, bool $visible): Image
    {
        $image = $this->createStub(Image::class);
        $image->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $asked): bool => $visible && $type === 'view' && $asked === $user
        );

        return $image;
    }

    /**
     * An object with a field `name` that records what is written into it.
     */
    private function writable(): Concrete
    {
        return new class() extends Concrete {
            /** @var array{0: mixed, 1: string|null}|null */
            public ?array $written = null;

            public function setName(mixed $name, ?string $language = null): static
            {
                $this->written = [$name, $language];

                return $this;
            }
        };
    }
}
