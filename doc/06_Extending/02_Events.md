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

## Studio Response Events

The configuration panel in Pimcore Studio dispatches a pre-response event before each of its API responses is returned,
under `Event\Studio\PreResponse`. Listen for them to enrich a response with additional attributes, for example
`ConfigurationDetailEvent`, `DataPreviewEvent` or `ConnectionsEvent`.

These events follow the pre-response pattern of the Studio Backend bundle. See
[Studio Integration](./03_Studio_Integration.md).
