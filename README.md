---
title: Data Importer
---

# Pimcore Data Importer

The Data Importer adds import functionality to Pimcore Datahub. It reads data from external sources and writes it into
Pimcore data objects based on a configured mapping, without writing code.

![Mapping](./doc/img/mapping.png)
*Mapping and preview of data*

## Features

- Import configurations managed directly in Datahub, edited in Pimcore Studio.
- Six data sources: asset, upload, HTTP, SFTP, push endpoint, and SQL.
- Five file formats: CSV, JSON, XML, XLSX, and SQL query results.
- Resolver strategies that decide whether a record updates an existing data object or creates a new one, where it is
  stored, and whether it is published.
- A mapping editor with a live preview of the source data and of every transformation step.
- Delta check to skip unchanged records, and cleanup to remove data objects that left the source.
- Manual, scheduled, command-line and push-triggered execution.
- Progress tracking and detailed import logs.

## Documentation

Start here:

- [Installation](./doc/01_Installation/README.md): install the bundle and set up queue processing.
- [Getting Started](./doc/02_Getting_Started.md): build a first import end to end.

Reference:

- [Configuration](./doc/03_Configuration/README.md): every option of an import configuration.
- [Import Execution Details](./doc/04_Import_Execution_Details.md): what happens between the trigger and the data.
- [Import Progress and Logging](./doc/05_Import_Progress_and_Logging.md): watch and audit an import.
- [Extending](./doc/06_Extending/README.md): custom strategies, events, and configuration validation.
- [MCP Tools](./doc/07_MCP_Tools.md): let AI agents build and maintain import configurations.
- [Troubleshooting / FAQ](./doc/08_Troubleshooting_FAQ.md): common problems.
- [Upgrade Notes](./doc/01_Installation/01_Upgrade.md): breaking changes per release.

## Other Datahub Adapters

- [Datahub (GraphQL API)](https://github.com/pimcore/data-hub/blob/2026.x/doc/01_Installation_and_Upgrade/README.md)
- [Datahub Simple REST API](https://github.com/pimcore/data-hub-simple-rest/blob/2026.x/doc/01_Installation/README.md)
- [Datahub File Export](https://github.com/pimcore/data-hub-file-export/blob/2026.x/doc/01_Installation/README.md)
- [Datahub Productsup](https://github.com/pimcore/data-hub-productsup/blob/2026.x/doc/01_Installation/README.md)
