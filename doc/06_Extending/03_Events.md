# Events

Another option to customize import behavior is listening for events. The bundle fires following events during the 
import process: 

- `Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent`: Fired just before an imported data object will be saved
- `Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent`: Fired just after an imported data object is saved
- `Pimcore\Bundle\DataImporterBundle\Event\DataObject\ProcessElementExceptionEvent`: Fired when an exception is thrown when processing an element

More events to come when needed (just provide PRs ;-) . 