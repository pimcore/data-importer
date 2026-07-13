---
title: Events
description: Hook into the import process with Symfony event listeners.
---

# Events

Listening for events customizes import behaviour without replacing any component. All event classes live under
`Pimcore\Bundle\DataImporterBundle\Event`.

## Import Events

| Event | Fired |
|---|---|
| `DataObject\PreSaveEvent` | Before an imported data object is saved. |
| `DataObject\PostSaveEvent` | After an imported data object is saved. |
| `DataObject\ProcessElementExceptionEvent` | When processing a record throws an exception. |
| `PostPreparationEvent` | After an import was prepared and the queue items were created. |

The three `DataObject` events share a base class exposing the import configuration name, the raw source record, and the
data object. `ProcessElementExceptionEvent` adds the thrown exception, the error message, and the mapping configuration
that failed, when the failure can be attributed to one.

`PostPreparationEvent` exposes the configuration name, the execution type, and whether the source file was interpreted.

## Example

Adjust a data object right before it is saved:

```php
namespace App\EventListener;

use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ImportListener
{
    public function __invoke(PreSaveEvent $event): void
    {
        if ($event->getConfigName() !== 'my-product-import') {
            return;
        }

        $rawData = $event->getRawData();
        $dataObject = $event->getDataObject();

        // adjust $dataObject based on $rawData
    }
}
```

## Studio API Events

The configuration panel is a Pimcore Studio plugin. Before one of its endpoints returns, the bundle dispatches a
pre-response event carrying the response schema. Listen for it to add your own data to the response.

### Configuration and Metadata

| Event | Returned data |
|---|---|
| `pre_response.data_importer.configuration_detail` | The full detail of one import configuration: its name, the configuration object (general, loader, interpreter, resolver, processing, mapping and execution settings), the current user's update and delete permissions, the modification date, and the available column headers. |
| `pre_response.data_importer.class_attributes` | The available data object attributes of the class an import configuration writes into. |
| `pre_response.data_importer.column_headers` | The column headers derived from the current preview data: index, data index and label per column. |

### Classification Store Lookups

| Event | Returned data |
|---|---|
| `pre_response.data_importer.classification_store_key_name` | The resolved group name and key name for a classification store key ID. |
| `pre_response.data_importer.classification_store_keys` | The classification store key-to-group relations matching a search, plus the total number of matches. |

### Preview and Transformation

| Event | Returned data |
|---|---|
| `pre_response.data_importer.data_preview` | One preview record's cell values per column (data index, label, value, and whether the column is mapped), plus the index of the record that was actually loaded. |
| `pre_response.data_importer.transformation_result_previews` | The rendered preview string of the transformation pipeline result for each mapping entry. |
| `pre_response.data_importer.transformation_result_type` | The data type a transformation pipeline evaluates to. |

### Import Execution

| Event | Returned data |
|---|---|
| `pre_response.data_importer.cron_validation` | Whether a cron expression entered in the Execution tab is valid, and an error message if it is not. |
| `pre_response.data_importer.import_file_status` | Whether an upload-type import file has already been uploaded for a configuration, a status message, and the file's storage path if it exists. |
| `pre_response.data_importer.import_progress` | Whether an import is currently running, the total and processed item counts, and progress as a ratio between 0 and 1. |
| `pre_response.data_importer.import_start` | Whether a manually triggered import was successfully prepared and started. |

### Lookups

| Event | Returned data |
|---|---|
| `pre_response.data_importer.connections` | The available Doctrine database connections (name and service identifier), offered to the SQL data source. |
| `pre_response.data_importer.unit_data` | The quantity value units (ID and abbreviation) offered for quantityValue data targets. |

See the Studio Backend
[Additional and Custom Attributes documentation](https://github.com/pimcore/studio-backend-bundle/blob/2026.x/doc/03_Extending/02_Additional_and_Custom_Attributes.md) for the listener pattern.

:::warning

The endpoints these events belong to are not a supported public API. They are marked `@internal`, exist to serve
Pimcore Studio, and may change in any release without a deprecation path. Do not build integrations against them.

:::
