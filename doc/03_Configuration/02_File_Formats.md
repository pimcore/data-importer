---
title: File Formats
description: How the importer parses the source data into records and fields.
---

# File Formats

The file format determines how the raw source data is split into records and fields. Set it in the **Data Source** step,
next to the [data source](./01_Data_Sources.md).

Each record becomes one queue item, and each field of that record can be addressed by a mapping entry through its
**data source index**. Depending on the format, that index is either a numeric position or a field name.

## What Interpretation Costs per Format

The interpretation step reads the source file and creates the queue items; the processing step that follows is
independent of the format. What that step costs differs sharply between the three row-oriented formats, and the
reasons are not the same, so the numbers below are three separate mechanisms rather than one scale.

| Format | Small (10,000 rows) | Large (100,000 rows) |
|---|---|---|
| CSV | 6 MB, 0.1 s | 6 MB, 0.3 s |
| XML | 10 MB, 0.1 s | 30 MB, 1.0 s |
| XLSX, whole-workbook read | 41 MB, 1.7 s | 306 MB, 10.6 s |
| XLSX, windowed read | 45 MB, 1.7 s | 117 MB, 40.3 s |

- **CSV** streams: it reads one line at a time and never holds the file, so its memory is flat in the row count.
- **XML** loads the document to walk the configured XPath, so its memory grows with the file, but one parsed
  element is far cheaper than one spreadsheet cell.
- **XLSX** builds one object per cell, which measured about 650 bytes per cell for the files above - some 100 times
  their size on disk, a factor that moves with how well the sheet compresses. This is what the windowed read
  described under [XLSX](#xlsx-excel) exists for, and why its threshold counts cells rather than bytes.

Which XLSX row applies depends on the threshold described under [XLSX](#xlsx-excel); a file at or below it takes
the whole-workbook read.

**How these were measured**

A generated file of five columns per row (text, text, decimal, integer, text) per format and row count. Each
number comes from its own PHP process, which interprets the file with a `PreQueueRowEvent` listener that skips
every row - so no queue, database or kernel is involved - and reports `memory_get_peak_usage(true)` and the
wall time of the interpretation step alone.

The memory figures are deterministic and reproduce to the decimal. The wall times are not: repeating the same
measurement on the same machine varied by up to a factor of two, so read them as orders of magnitude and as
ratios between the rows, not as a benchmark.

## CSV

**Configuration options**

- **Skip First Row**: skips the first row so it is not imported as data. In the [import preview](./03_Import_Preview.md)
  the values of that row label the columns.
- **Save Header Name**: makes fields addressable by their header name instead of their numeric position. Requires
  **Skip First Row**.
- **Delimiter**: defaults to `,`.
- **Enclosure**: defaults to `"`.
- **Escape Character**: defaults to `\`.

The interpreter uses PHP's [`fgetcsv`](https://www.php.net/manual/en/function.fgetcsv.php).

:::tip

Enable **Skip First Row** and **Save Header Name** together. Mappings then reference `sku` rather than `0`, which
survives a reordering of the columns in the source file.

:::

## JSON

The interpreter expects an array of JSON objects and reads every first-level attribute as a separate field. A field
holding a sub-object, such as `technical_attributes` below, arrives as an array in one field and has to be handled in the
[transformation pipeline](./05_Mapping_Configuration/01_Transformation_Pipeline.md).

**Sample file**

```json
[
    {
        "title_de": "Voluptas et est voluptas.",
        "title_en": "Animi ipsam rem et sed vel voluptas.",
        "technical_attributes": {
            "1-6": "value 1",
            "2-4": "value 2"
        }
    },
    {
        "title_de": "Et alias nesciunt ea mollitia nihil mollitia corporis."
    }
]
```

The interpreter uses [`json_decode($content, true)`](https://www.php.net/manual/en/function.json-decode.php).

**Configuration options**

- **JMESPath**: optional [JMESPath](https://jmespath.org) expression that selects the record array from the document
  before processing. Leave it empty when the document root is already an array of objects. The expression is not
  validated when the configuration is saved. A syntax error surfaces the first time the interpreter runs, in the import
  preview or during the import itself. Load the preview after changing the expression to check it.

### Selecting Records with JMESPath

Use a JMESPath expression when the record list is nested inside a wrapper object.

**Records nested under a key**

```json
{
    "meta": { "total": 2, "page": 1 },
    "items": [
        { "sku": "A-001", "title_de": "Produkt Eins", "price": 9.99 },
        { "sku": "A-002", "title_de": "Produkt Zwei", "price": 19.99 }
    ]
}
```

Set **JMESPath** to `items` to pass the `items` array to the import pipeline.

**Records in a deeply nested structure**

```json
{
    "catalog": {
        "products": {
            "product": [
                { "sku": "A-001", "title_de": "Produkt Eins", "price": 9.99 },
                { "sku": "A-002", "title_de": "Produkt Zwei", "price": 19.99 }
            ]
        }
    }
}
```

The expression `catalog.products.product` selects the nested array.

**Filtering records**

JMESPath also filters. To import only products priced above 10:

```
items[?price > `10`]
```

For the full expression reference see [jmespath.org](https://jmespath.org) and the
[`mtdowling/jmespath.php`](https://github.com/jmespath/jmespath.php) library.

## XLSX (Excel)

**Configuration options**

- **Skip First Row**: skips the first row so it is not imported as data. In the import preview the values of that row
  label the columns. Fields stay addressable by their numeric position.
- **Sheet Name**: name of the sheet to import. Defaults to `Sheet1`.

The interpreter uses [phpspreadsheet](https://phpspreadsheet.readthedocs.io/en/latest).

**How the sheet is read**

A sheet is loaded as a whole, which is the fastest way to read it. Above
`pimcore_data_importer.xlsx_interpreter.windowed_read_cell_threshold` cells (rows times columns, `200000` by
default, see [Installation](../01_Installation/README.md)) the interpreter reads the sheet in row windows instead.
Both reads produce exactly the same rows; they differ only in what they cost. Measured on a generated file of five
columns, interpreting it into queue items without processing them:

| Rows (cells) | Whole-workbook read | Windowed read |
|---|---|---|
| 10,000 (50,005) | 41 MB, 1.7 s | 45 MB, 1.7 s |
| 40,000 (200,005) | 132 MB, 4.9 s | 68 MB, 10.5 s |
| 100,000 (500,005) | 306 MB, 10.6 s | 117 MB, 40.3 s |

The windowed read trades roughly a third of the memory for several times the interpretation time, so it is worth
having only for files that would otherwise run into the memory limit. Lower the threshold when large imports
exhaust the limit, raise it to keep more imports on the faster read. The memory figures reproduce exactly; the wall
times vary with the machine and with the run, see
[what interpretation costs per format](#what-interpretation-costs-per-format) for the recipe behind them.

The threshold counts cells rather than bytes on disk because the memory a sheet needs follows its cell count, not
its file size: phpspreadsheet builds one object per cell, which measured about 650 bytes per cell for the files
above. The same 100,000-row file is 3.0 MB on disk and needs 306 MB to read - a ratio that moves with the width and
content of the rows and with how well they compress, so a threshold on file size would mispredict constantly.

The windowed read lowers peak memory but cannot make it flat: phpspreadsheet parses the whole sheet XML before a
read filter is consulted, so the parse alone sets a floor. For the 100,000-row file above that floor is 72 MB -
loading zero rows of it costs the same 72 MB as loading one row or a thousand. Shrinking the window below that
point costs interpretation time without saving memory.

**Formula cells**

A formula cell is imported as the result cached in the file by the application that wrote it, the same rule the
[import preview](./03_Import_Preview.md) applies, and the same rule on both reads. Only when a cell carries no
cached result is the formula calculated, on the rows currently loaded, and the cell is reported as a warning in the
[import log](../05_Import_Progress_and_Logging.md). At most 50 such cells are listed per import run, followed by one
line stating how many further cells were affected.

A calculated value can differ from the one a spreadsheet application shows, because references to another sheet are
never available and, on the windowed read, references to rows outside the current window are not either. This is
the only respect in which the two reads can answer differently, and it only concerns formula cells that carry no
cached result.

## XML

The interpreter expects a list of data elements at the configured XPath and reads every first-level child of a data
element as a separate field. A child holding sub-elements, such as `technical_attributes` below, arrives as an array in
one field and has to be handled in the
[transformation pipeline](./05_Mapping_Configuration/01_Transformation_Pipeline.md).

**Configuration options**

- **XPath**: XPath to the elements to import. For the sample below it is `/root/item`.
- **Schema**: XSD schema to validate the import data against. Without it, no validation takes place.

**Sample file**

```xml
<?xml version="1.0"?>
<root>
  <item>
    <title_de>Et voluptas culpa et incidunt laborum repellat.</title_de>
    <title_en>Aliquam et voluptas nemo at excepturi.</title_en>
    <technical_attributes>
      <attribute>
        <key>1-6</key>
        <value>Myrtle Kovacek</value>
      </attribute>
      <attribute>
        <key>2-4</key>
        <value>Ut.</value>
      </attribute>
    </technical_attributes>
  </item>
</root>
```

The interpreter uses [Symfony `XmlUtils`](https://github.com/symfony/config/blob/master/Util/XmlUtils.php) to read and
validate the data. A numeric looking value is only converted to a number when the number prints as exactly the
same text: `42` becomes an integer, while `0123`, `1E5`, `12.50` or `603E709` stay text. Boolean (`true`, `false`)
and `null` values are converted as before.

## SQL

Reads the result of the query configured in the [SQL data source](./01_Data_Sources.md#sql). Each result row becomes a
record, and each selected column becomes a field addressable by its column name. There is nothing else to configure.

Select this format whenever the data source is **SQL**.

## Custom File Formats

Add a file format of your own with a custom interpreter. See
[Custom Strategies](../06_Extending/01_Custom_Strategies.md).
