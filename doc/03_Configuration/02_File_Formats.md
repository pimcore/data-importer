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

