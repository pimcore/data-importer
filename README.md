# Pimcore Datahub Batch Import

This extension adds a comprehensive import functionality to Pimcore Datahub. It allows importing data from external 
sources and applying the data to Pimcore data objects based on a configured mapping without writing any code.

## Features in a nutshell
- Configure multiple imports directly in Datahub. 
- Import data from different data sources like
  - remote SFTP location.
  - remote HTTP location.
  - Pimcore Assets.
  - receiving data push from remote location.
- Supported File Formats: CSV, XLSX, JSON, XML.
- Upload Preview file and apply settings accordingly.
- Configure strategies for ... 
  - loading existing elements for updating data.
  - defining location for newly imported data.
  - publishing data.
  - cleanup of existing data. 
- Define mappings for applying data to Pimcore data objects with
  - simple transformations.
  - preview of imported data.
  - different targets like direct class attributes, object brick attributes and classification store attributes.
- Execution of imports 
  - directly in Pimcore Datahub. 
  - on regular base via cron definitions. 
- Import status updates and extensive logging information. 

![Data Source](doc/img/datasource.png)
![Mapping](doc/img/mapping.png)
![Execution](doc/img/execution.png)

## Further Information
- [Installation](doc/01_Installation.md)
- [Configuration](doc/03_Configuration/README.md)
- [Extending](doc/05_Extending/README.md)
