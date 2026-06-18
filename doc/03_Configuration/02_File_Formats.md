# File Formats

The source data needs to be in an interpretable format for the importer. Following interpreters can 
be configured: 

### CSV
The source data is interpreted as CSV. 

##### Configuration Options: 
- **Skip First Row**: If activated, first row will be used as column names and not interpreted as data row. 
- **Delimiter**
- **Enclosure**
- **Escape**

Internally the adapter uses [`fgetcsv`](https://www.php.net/manual/en/function.fgetcsv.php) function of php. 

### JSON
The source data is interpreted as JSON. 

The adapter expects an array of json objects and reads all first level attributes as separate fields. 
If one field contains sub objects (like `technical_attributes`) in the example below, this object 
is interpreted as array in one field (and needs to be considered in the transformation pipeline). 

**Sample File**: 
```json 
[
    {
        "title_de": "Voluptas et est voluptas.",
        "title_en": "Animi ipsam rem et sed vel voluptas.",
        ...
		"technical_attributes": {
			"1-6": "value 1",
			"2-4": "value 2"
		}
    },
    {
        "title_de": "Et alias nesciunt ea mollitia nihil mollitia corporis.",
        ...
    },
]
```

Internally the adapter uses [`json_decode($content, true)`](https://www.php.net/manual/en/function.json-decode.php) function of php. 

##### Configuration Options:
- **JMESPath**: Optional. A [JMESPath](https://jmespath.org) expression used to select the array of 
  records from the JSON document before processing. If left empty, the root of the document is used 
  directly (which must itself be an array of objects).

#### Using JMESPath to Select Records

By default the adapter expects the root of the JSON document to be an array of objects. For JSON 
structures where the record list is nested inside a wrapper object, a JMESPath expression can be 
configured to extract it first.

**Example: records nested under a key**

Given a file like:
```json
{
    "meta": { "total": 2, "page": 1 },
    "items": [
        { "sku": "A-001", "title_de": "Produkt Eins", "price": 9.99 },
        { "sku": "A-002", "title_de": "Produkt Zwei", "price": 19.99 }
    ]
}
```

Setting **JMESPath** to `items` causes the adapter to pass the `items` array to the import pipeline, 
rather than attempting to iterate the root object.

**Example: records in a deeply nested structure**

Given:
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

**Example: filtering records with JMESPath**

JMESPath also supports filtering. To import only products that have a price greater than 10:

```
items[?price > `10`]
```

For a full reference of supported expressions see [jmespath.org](https://jmespath.org) and the 
[`mtdowling/jmespath.php`](https://github.com/jmespath/jmespath.php) library documentation.


### XLSX (Excel)
The source data is interpreted as XLSX Excel file. 

##### Configuration Options: 
- **Skip First Row**: If activated, first row will be used as column names and not interpreted as data row. 
- **Sheet**: Name of data sheet to be imported.
 
Internally the adapter uses [`phpspreadsheet`](https://phpspreadsheet.readthedocs.io/en/latest) to read the data.


### XML
The source data is interpreted as XML.

The adapter expects a list of data elements at the configued xpath and reads all first level elements 
of the data elements as separate fields. If one element contains sub elements (like `technical_attributes`) in the example 
below, this the sub elements are interpreted as array in one field (and needs to be considered in the transformation pipeline).


##### Configuration Options: 
- **XPath**: XPath to the elements to be imported. For the sample below it would be `/root/item`. 
- **Schema**: XSD Schema the import data should be validated against. If not defined, to validation takes place.

**Sample File**: 
```xml
<?xml version="1.0"?>
<root>
  <item>
    <title_de>Et voluptas culpa et incidunt laborum repellat.</title_de>
    <title_en>Aliquam et voluptas nemo at excepturi.</title_en>
    ...
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
  <item>
    ...
  </item>
</root>
```

Internally the adapter uses [Symfony `XmlUtils`](https://github.com/symfony/config/blob/master/Util/XmlUtils.php) to read 
and validate the data.

### Custom File Formats
You can import any file format using custom adapters.

