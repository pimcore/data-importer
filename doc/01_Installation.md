# Installation
This bundle depends on Pimcore [Datahub bundle](https://github.com/pimcore/data-hub). This needs
to be installed first.

To install Pimcore Data Importer use following commands:

```bash
composer require pimcore/data-importer --with-all-dependencies
./bin/console pimcore:bundle:enable PimcoreDataImporterBundle
```

> Make sure, that priority of Datahub bundle is higher than priority of Data Importer bundle.
> This can be specified as parameter during bundle enablement or in Pimcore extension manager.
 

## Bundle Configuration

### Import Execution
The imports are executed asynchronously in the background. The processing can be done via executing commands on a regular 
base or by utilizing symfony messenger. Either of the two needs to be configured. 

#### Command Based
For command based importing, following commands need to be executed on regular base. The actual interval depends
on use cases and system environment. 

```bash
# Process import queue items that can be executed in parallel
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-parallel --processes=5
# Process import queue items that need to be executed sequentially 
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-sequential 
```

See [Import Execution Details](04_Import_Execution_Details.md) for more information about sequential and parallel execution.


#### Symfony Messenger Based
For symfony messenger based importing, at least following configuration needs to be done in symfony configuration: 
```yml 
pimcore_data_importer:
    messenger_queue_processing:
        activated: true
```

In addition to that, following settings are available. They all have meaningful default values though: 
- `worker_count_parallel`: Count of maximum parallel worker messages for parallel imports.
- `worker_item_count`: Count of items imported per worker message.
- `worker_count_lifetime`: Lifetime of tmp store entry for current worker count entry. After lifetime, the value will be cleared.


### Cron Execution
Import configuration can be setup to be executed on regular base by defining a cron definition. To make sure, the cron 
definitions are checked on regular base, following command needs to be executed on regular base. The actual interval depends 
on use cases and system environment; the shorter, the more accurate the import execution will take place. 
```bash 
# Check cron configurations and execute necessary import definitions
* * * * * php /home/project/www/bin/console datahub:data-importer:execute-cron
```
