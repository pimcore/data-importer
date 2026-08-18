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
| `PreInterpretFileEvent` | Before the interpreter starts reading the source file. |
| `PreQueueRowEvent` | For every extracted row, before it is added to the processing queue. |
| `DataObject\PreSaveEvent` | Before an imported data object is saved. |
| `DataObject\PostSaveEvent` | After an imported data object is saved. |
| `DataObject\ProcessElementExceptionEvent` | When processing a record throws an exception. |
| `PostPreparationEvent` | After an import was prepared and the queue items were created. |

The three `DataObject` events share a base class exposing the import configuration name, the raw source record, and the
data object. `ProcessElementExceptionEvent` adds the thrown exception, the error message, and the mapping configuration
that failed, when the failure can be attributed to one.

`PostPreparationEvent` exposes the configuration name, the execution type, and whether the source file was interpreted.

## Interpretation-Stage Events

The two interpretation-stage events customize how the source file turns into import rows without writing a custom
interpreter. Both expose the configuration name and the execution type, and both are also dispatched (with
`isPreview()` returning `true`) when Pimcore Studio renders the source preview and the available mapping columns, so
the configuration UI shows exactly the data an actual import would produce.

### PreInterpretFileEvent

Dispatched before the interpreter validates and reads the source file. `setPath()` replaces the file that gets
interpreted - use it to normalize a file (transcode it, strip a report preamble, rewrite delimiters) while keeping the
standard interpreter. It also marks the start of an interpretation run, which stateful `PreQueueRowEvent` listeners can
use as a reset signal.

### PreQueueRowEvent

Dispatched for every row the interpreter extracted, right before the row is added to the processing queue. The listener
receives the row exactly as the interpreter produced it - before the delta check, the resolver's identifier extraction,
and the mapping pipeline - so changed values (including the ID column) affect which element a row resolves to.

| Method | Purpose |
|---|---|
| `getOriginalRow()` | The row as extracted, unaffected by other listeners. |
| `getRows()` / `setRows(array $rows)` | The rows that will be queued. Set one row to modify it, an empty array to skip it, or multiple rows to fan the source row out into multiple elements. |
| `skipRow(bool $keepInCleanupIdentifierCache = false)` | Skip the row. See the cleanup warning below. |

```php
namespace App\EventListener;

use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ProductRowListener
{
    public function __invoke(PreQueueRowEvent $event): void
    {
        if ($event->getConfigName() !== 'my-product-import') {
            return;
        }

        $row = $event->getOriginalRow();

        // skip discontinued products, but keep their existing objects
        if (($row['status'] ?? '') === 'discontinued') {
            $event->skipRow(keepInCleanupIdentifierCache: true);

            return;
        }

        // add a computed column that mapping, resolver and location strategies can use
        $row['path'] = '/products/' . $row['category'] . '/' . $row['sku'];

        // fan out: one source row per configured sales channel becomes one element each
        $rows = [];
        foreach (explode(',', $row['channels']) as $channel) {
            $rows[] = ['channel' => $channel] + $row;
        }

        $event->setRows($rows);
    }
}
```

:::warning

When the import uses an active cleanup strategy, every element whose identifier is not seen during interpretation is
deleted or unpublished. A skipped row's element counts as "not seen". Skip rows with
`skipRow(keepInCleanupIdentifierCache: true)` when their existing elements must survive the cleanup.

:::

Custom interpreters get both events automatically as long as they extend `AbstractInterpreter` (or provide a
`setEventDispatcher()` method) - the interpreter compiler pass wires the dispatcher into every tagged interpreter
service.

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
