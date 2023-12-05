# Resolver Settings

Resolver settings are responsible to define to which Pimcore data imported data should
be imported to and consists of following parts: 

<div class="image-as-lightbox"></div>

![Resolver Settings](../img/resolver_settings.png)

### Class
Define the Pimcore data object class of the imported data.

### Element Loading
Define a strategy the importer should use for looking for existing Pimcore data objects in order
to update them instead of creating new data objects.

Following strategies are available: 

#### Loading Strategy: `No Loading`
Does not look for any existing Pimcore data objects. Import always creates new data objects. 

#### Loading Strategy: `Id`
Look for data objects based on their id. 
- **Data Source Index**: Field of import that contains the id to look for. 

#### Loading Strategy: `Path`
Look for data objects based on their full path. 
- **Data Source Index**: Field of import that contains the path to look for. 

#### Loading Strategy: `Attribute`
Look for data objects based on a specific attribute (e.g. Remote Id, EAN, ...). 
- **Data Source Index**: Field of import that contains the attribute value to look for. 
- **Attribute Name**: Attribute of data object to look for. 

#### Loading Strategy: `Composed Path`

This allows loading objects using the **Composed Path** syntax described below.

Using the example Excel file in the **Composed Path** section you could load the Data Object at `/Products/Cars/GMC/Sierra/2015` using Path Syntax `/Products/Cars/$[1]/$[2]/$[0]`.  Note that any mapped in values that are not valid for the key field will be passed through `ElementService::getValidKey` to ensure a valid key is created when performing the lookup.

#### Property

This allows a data object to be loaded based on the value of a property stored on it.

**This assumes that the property value is unique**. If a non-unique value exists, it'll be a random object returned that matches the criteria.


### Element Creation 
Define location of new created data objects. 

#### Location Strategy: `Static Path`
Always put new elements to a fixed specific folder. 
- **Path**: Folder where to put new elements to. 

#### Location Strategy: `Find Parent`
Find parent based on a strategy. 
- **Find Strategy**: Strategy find to parent
  - Loading Strategy `Id`: Load based on id. 
  - Loading Strategy `Path`: Load based on full path.
  - Loading Strategy `Attribute`: Load based on data attribute with additional settings:
    - Class: Data object class to look for (can be different one that the imported data object class)
    - Attribute Name: Attribute of data object to look for. 
- **Data Source Index**: Field of import that contains the value to look for.
- **Fallback Path**: Folder to use if parent cannot be found.

#### Location Strategy: `Composed Parent`

This allows locating objects using the **Composed Path** syntax described below.

Using the example Excel file in the **Composed Path** section you could create a Data Object with parent `/Products/Cars/GMC/Sierra/2015` using Path Syntax `/Products/Cars/$[1]/$[2]/$[0]`. 


### Element Location Update
Define location updates of data objects. The importer applies the location update strategy to all imported data objects -
no matter if they are updated or created. 

For details on the strategies see Element Creation above. In addition, there is a `No Change` strategy that does not change
the location of elements at all. 


### Element Publishing
Define a strategy to set the published state of data object during import. 

Following strategies are available: 
- **Always Publish**: Always publishes new created or updated data objects.
- **Attribute Based**: Set publish state based on a field of the import data.
- **No Change, Publish New**: Do not change existing data objects and set new data objects to `published`.
- **No Change, Unpublish New**: Do not change existing data objects and set new data objects to `unpublished`. 


### Composed Paths

Any Loading or Location strategy that references **Composed Paths** allows for paths to be created based on values in the import. 

Take for example an Excel File:
| Year | Make | Model | Color |
| ---  | ---  | ---   | ---   |
| 2015 | GMC  | Sierra| White |
| 2001 | Chevrolet | Silverado | Blue |

To build the Path `/Products/Cars/GMC/Sierra/2015` using the Composed Path Syntax would be `/Products/Cars/$[1]/$[2]/$[0]`. The numerical values correspond to the indexes of the values in the Excel file (starting at 0).

For an XML file:

```
<Cars>
    <Car>
        <Make>GMC</Make>
        <Model>Sierra</Model>
        <Year>2015</Year>
        <Color>White</Color>
    </Car>
    <Car>
        <Make>Chevrolet</Make>
        <Model>Silverado</Model>
        <Year>2001</Year>
        <Color>Blue</Color>
    </Car>
</Cars>
```
the **Composed Path Syntax** would use the Attribute names instead `/Products/Cars/$[Make]/$[Model]/$[Year]`