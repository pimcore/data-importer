---
title: Apply a Mapping Without Saving
description: Apply a mapping configuration to an element in memory, e.g. to preview it or to record the result elsewhere.
---

# Apply a Mapping Without Saving

`Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier` applies the mapping of an import configuration to an
element you pass in, for one import row at a time. Use it when you want the result of a mapping but not an import, for
example to preview it or to record the changes somewhere other than the element itself.

Compared to an import, applying a mapping:

- takes the mapping as an array, so no stored configuration is needed,
- uses no queue and no resolver: loading, creating and placing the element is up to you, so location strategies (including
  the ones that create folders) do not apply,
- does not save the element, refuses to save or delete any element while it runs, and dispatches no `PreSaveEvent` or
  `PostSaveEvent`,
- writes nothing to the application logger. `apply()` returns what the operators would have logged instead, see
  [Warnings](#warnings).

## Usage

`prepare()` builds the mapping once. The returned `PreparedMapping` can be applied to any number of elements and rows.

```php
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

final class ProductPreview
{
    public function __construct(private readonly MappingApplier $mappingApplier)
    {
    }

    public function preview(array $mappingConfig, int $productId, array $row): ElementInterface
    {
        // throws an InvalidConfigurationException listing every issue lint() reports
        $mapping = $this->mappingApplier->prepare($mappingConfig);

        // a copy of its own, so the instance other code gets from getById() stays unchanged
        $product = DataObject\Concrete::getById($productId);
        $copy = Service::cloneMe($product);
        $copy->setId($product->getId());
        $copy->setParentId($product->getParentId());

        $warnings = $mapping->apply($copy, $row); // see Warnings below

        return $copy;
    }
}
```

`$mappingConfig` has the shape of the `mappingConfig` of a stored import configuration: a list of items with
`dataSourceIndex`, `transformationPipeline` and `dataTarget`. `$row` is keyed like the rows the Data Importer reads from
the source, so `dataSourceIndex` refers to the same columns.

When an item fails, `apply()` throws a `MappingApplicationException`. `getItemIndex()` and `getItemLabel()` name the
failing item, `getPrevious()` holds the original exception. Items before it have already been applied to the element.

### Warnings

`apply()` returns a list of `MappingIssue` objects (`itemIndex`, `itemLabel`, `message`) with what the operators would
have written to the application logger during an import, in the order of the items, for example that a reference could
not be resolved. An empty list means nothing was reported.

An item with a warning is still applied. An operator that cannot resolve a reference returns `null`, and the **Direct**
data target writes it unless `writeIfSourceIsEmpty` is disabled, which clears a relation the element already has.

### Which element to pass

The element is changed in memory only. The data targets read its current values, so the result depends on the element
you pass:

- the **Direct** data target keeps a value when `writeIfTargetIsNotEmpty` is disabled and the field is not empty, and when
  `writeIfSourceIsEmpty` is disabled and the source is empty,
- the **Many-to-Many Relation** data target in merge mode adds to the relations the element already has,
- the classification store data targets add to the active groups the element already has.

Pass the element whose current state the result should build on. If other code in the same process must not see the
changes, pass a copy you own, as in the example above: `getById()` returns the instance Pimcore keeps in its runtime
cache, and loading with `force` puts the new instance there as well.

## Check a Mapping Up Front

`lint()` returns a list of `MappingIssue` objects (`itemIndex`, `itemLabel`, `message`) without applying anything. An
empty list means `prepare()` accepts the mapping. It reports:

- items that cannot be built, for example an unknown operator or data target type, or invalid settings,
- operators and data targets that write elements while processing a row.

```php
foreach ($mappingApplier->lint($mappingConfig) as $issue) {
    echo $issue, PHP_EOL; // Mapping item 2 (`Image`): Operator `importAsset` writes elements and cannot be applied without saving.
}
```

The shipped **Import Asset** operator saves assets and creates folders, so a mapping using it cannot be applied without
saving. Use **Load Asset** instead. A custom operator or data target that saves, creates or deletes elements has to
implement `Pimcore\Bundle\DataImporterBundle\Mapping\WritesElementsInterface`, so that `lint()` and `prepare()` reject
it. Without it, `apply()` throws a `MappingApplicationException` when the operator or data target saves or deletes an
element. This holds for a [reference lookup](#resolve-references-yourself) as well: it runs during `apply()`.

## Resolve References Yourself

The **Load Data Object** and **Load Asset** operators look up elements with their configured load strategy. Pass a
`ReferenceLookupInterface` to `apply()` to resolve these references first, for example to point them to elements that are
not saved yet:

```php
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLookupInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceType;
use Pimcore\Model\Element\ElementInterface;

final class PendingProductLookup implements ReferenceLookupInterface
{
    /**
     * @param array<string, ElementInterface> $pendingByPath
     */
    public function __construct(private readonly array $pendingByPath)
    {
    }

    public function find(ReferenceQuery $query): ?ElementInterface
    {
        if ($query->type !== ReferenceType::DataObject || $query->loadStrategy !== ReferenceLoadStrategy::Path) {
            return null;
        }

        return $this->pendingByPath[$query->key] ?? null;
    }
}

$mapping->apply($product, $row, new PendingProductLookup($pendingByPath));
```

The operator asks the lookup once per value, before its own load strategy. When the lookup returns `null`, the operator
falls back to its own strategy. The returned element is used as is, so it may be unsaved. It has to be a data object for
`ReferenceType::DataObject` and an asset for `ReferenceType::Asset`.

Unsaved elements work for fields that keep the element itself: many-to-one relations and many-to-many relations. The
**Many-to-Many Relation** data target in merge mode tells saved elements apart by id and elements without an id by
instance, so the same unsaved instance is added once. Advanced many-to-many relations keep only the id of a related
element and load it again, so they need saved elements: `apply()` throws a `MappingApplicationException` for an element
that cannot be loaded by its id.

`ReferenceQuery` describes what the operator is looking for:

| Property | Content |
|---|---|
| `type` | `ReferenceType::DataObject` or `ReferenceType::Asset` |
| `loadStrategy` | `ReferenceLoadStrategy::Id`, `Path` or `Attribute`, as configured on the operator |
| `key` | The value the operator would look up: trimmed for `Id` and `Path`, unchanged for `Attribute` |
| `classId`, `attributeName`, `attributeLanguage`, `partialMatch` | The attribute settings of **Load Data Object**, set for `Attribute` only |
| `includeUnpublished` | The **Load unpublished** setting of **Load Data Object** |

The lookup only applies during the `apply()` call it is passed to. It is removed when the call returns or throws, and
imports never use it.

## Read the Rows of a File

`Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader` reads a source file with the file format of an import
configuration and returns the rows an import would queue, without queueing them. Use it to check a file before
importing it, or to pass its rows to `apply()`:

```php
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;

$interpreterConfig = ['type' => 'xlsx', 'settings' => ['sheetName' => 'Sheet1', 'skipFirstRow' => true]];

foreach ($sourceFileReader->readRows($interpreterConfig, $path) as $row) {
    $mapping->apply($product, $row);
}
```

`$interpreterConfig` has the shape of the `interpreterConfig` of a stored import configuration. The rows are keyed like
the rows of an import, so the `dataSourceIndex` of a mapping refers to the same columns, and they follow the same
settings: with `skipFirstRow` the header row is left out, without it the header row is the first row. Unlike an import,
reading rows skips no unchanged rows (delta check), cleans up no elements and writes nothing to the application logger.
A file that is not valid for the format or lacks the configured sheet throws an `InvalidInputException`, and so does a
row that is not UTF-8 encoded or, with `saveHeaderName`, has not as many columns as the CSV header row. The message
names the row, counted from 1 with the header row.

The **CSV** and **XLSX** file formats support reading rows. Other formats throw an `InvalidConfigurationException`; a
custom file format can support it by implementing
`Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\RowReaderInterface`.

### Typed Values

An import receives every XLSX cell as text, so the number `123` arrives as `'123'` and `TRUE` as `'TRUE'`, and a number
cannot be told apart from text. Pass `true` as the third argument to get the stored values instead:

| Cell | Default | Typed |
|---|---|---|
| Number `123` | `'123'` | `123` |
| Text `00123` | `'00123'` | `'00123'` |
| Number `12.5` | `'12.5'` | `12.5` |
| Boolean `TRUE` | `'TRUE'` | `true` |
| Date `2026-03-01` | `'46082'` | `46082` |
| Empty | `null` | `null` |
| Formula `=1+1` | `'2'` | `2` |

Number formats are not read, so a number displayed as `00123` is `'123'` or `123`, and dates are Excel serial numbers;
convert them with `PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject()`. Imports always use the default. CSV
values are strings either way, the option changes nothing for them.
