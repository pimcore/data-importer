---
title: File Formats
description: How the importer parses the source data into records and fields.
---

# File Formats

The file format determines how the raw source data is split into records and fields. Set it in the **Data Source** step,
next to the [data source](./01_Data_Sources.md).

Each record becomes one queue item, and each field of that record can be addressed by a mapping entry through its
**data source index**. Depending on the format, that index is either a numeric position or a field name.

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
  before processing. Leave it empty when the document root is already an array of objects. An invalid expression is
  rejected when the configuration is saved.

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
validate the data.

## SQL

Reads the result of the query configured in the [SQL data source](./01_Data_Sources.md#sql). Each result row becomes a
record, and each selected column becomes a field addressable by its column name. There is nothing else to configure.

Select this format whenever the data source is **SQL**.

## Custom File Formats

Add a file format of your own with a custom interpreter. See
[Custom Strategies](../06_Extending/01_Custom_Strategies.md).
