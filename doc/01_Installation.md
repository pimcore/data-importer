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
Configure following commands to be executed on a regular base. The actual interval depends on use cases and system environment.
```bash 
# Check cron configurations and execute necessary import definitions
* * * * * php /home/project/www/bin/console datahub:data-importer:execute-cron
# Process import queue items that can be executed in parallel
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-parallel --processes=5
# Process import queue items that need to be executed sequentially 
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-sequential 
```
See [Import Execution Details](04_Import_Execution_Details.md) for more information about sequential and parallel execution. 
