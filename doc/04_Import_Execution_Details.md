# Import Execution Details

Every import execution consists of two major parts
1) Preparation
2) Processing 


### 1) Preparation
Import preparation loads import source from configured data source, interprets the file based on the file format settings
and splits import source into single data rows. These data rows are added to a queue to be processed during import processing. 

Preparation starts when 'import is starte', that means ...  
- when clicking manual execution in `Execution` tab of import configuration UI. 
- based on cron definition for import when `datahub:data-importer:execute-cron` command is executed. Every command execution calls
 preparation of all import configuration, that are or were due since the last command execution.  
- when `datahub:data-importer:prepare-import` is executed for a certain import configuration.
- when data is pushed to the corresponding endpoint via http when `push` data source is configured. 

For starting imports see also [Execution Configuration](./03_Configuration/07_Execution_Configuration.md).

Steps to be executed while preparation are: 
- Loading data from data source.
- Interpret source data, spilt into data rows and create queue items.
- If activated: check if existing elements in Pimcore need to be cleaned up and create corresponding cleanup queue items. 

Preparation is executed only when the queue for corresponding import configuration is empty to prevent race conditions 
during import.  


### 2) Processing

There are two types of processing - sequential and parallel - which are configured in the import configuration. 

Sequential imports process the queue items one by one in the order they were added. This might be necessary when data elements
depend on each other, e.g. when building up hierarchies etc. 

Parallel imports process the queue items in a parallelized way. This speed things up, but queue items will not be processed
in their exact order. 

For executing processing of sequential and parallel queue items, two commands 
(`datahub:data-importer:process-queue-sequential` and `datahub:data-importer:process-queue-parallel`) are available.

The actual processing is the same for both types and consists of following steps for import jobs: 
- Look for existing data element based on loading strategy or create new data element.
- Update location of data element based on location update strategy.
- Update published state of element based on publish strategy.
- Process mappings and transformation pipelines for each mapping.
- Assign transformation result to data element based on data target definition.

Cleanup jobs are simpler and consist of following steps: 
- Look for existing data element based on loading strategy.
- Cleanup element (unpublish or delete) based on cleanup strategy.

To learn about all different configuration options and strategies in detail, see the [Configuration Docs](./03_Configuration/README.md).
