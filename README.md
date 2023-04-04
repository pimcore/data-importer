---
title: Data Importer
---
 
# Pimcore Data Importer

This extension adds a comprehensive import functionality to Pimcore Datahub. It allows importing data from external 
sources and adjusting it to Pimcore Data Objects based on a configured mapping without writing any code.

## Features in a Nutshell
- Multiple imports configuration directly in Datahub. 
- Data import from various data sources.
- Supported File Formats: `csv`, `xlsx`, `json`, `xml`.
- Strategies configuration for: 
  - loading existing elements for updating data.
  - defining location for newly imported data.
  - publishing data.
  - cleanup of existing data. 
-  Mappings definition for adjusting data to Pimcore Data Objects with:
   - simple transformations.
   - preview of imported data.
- Imports execution directly in Pimcore Datahub or on a regular base via cron definitions. 
- Import status updates and extensive logging information. 

![Mapping](./doc/img/mapping.png)
*Mapping and preview of data*

## Documentation Overview
- [Installation](./doc/01_Installation.md)
- [Configuration](./doc/03_Configuration/README.md)
- [Import Execution Details](./doc/04_Import_Execution_Details.md)
- [Import Progress and Logging](./doc/05_Import_Progress_and_Logging.md)
- [Extending](./doc/06_Extending/README.md)
- [Troubleshooting/FAQ](./doc/06_Troubleshooting_FAQ.md) 

## Further Information
On other Pimcore Datahub adapters and export solutions:
- [Datahub (GraphQL API)](https://pimcore.com/docs/platform/Datahub/)
- [Datahub Simple Rest API](https://pimcore.com/docs/platform/Datahub_Simple_Rest/)
-  [Datahub File Export](https://pimcore.com/docs/platform/Datahub_File_Export/)
- [Datahub Productsup](https://pimcore.com/docs/platform/Datahub_Productsup/)
- [Datahub CI Hub](https://pimcore.com/docs/platform/Datahub_CI_Hub/)