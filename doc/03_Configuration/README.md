---
title: Configuration
description: Reference for every option of an import configuration.
---

# Configuration

Edit an import configuration in Pimcore Studio under **Automation & Integration** > **Data Hub Configuration**. Its
**Data Setup** tab is a five-step wizard, and the chapters below follow that order. The **Execution** tab, covered by the
last chapter, controls when an import runs.

| Step in Pimcore Studio | Chapter | Answers |
|---|---|---|
| Data Source | [Data Sources](./01_Data_Sources.md) | Where does the data come from? |
| Data Source | [File Formats](./02_File_Formats.md) | How is the data parsed? |
| Preview Import | [Import Preview](./03_Import_Preview.md) | What does one record look like? |
| Resolver | [Resolver Settings](./04_Resolver_Settings.md) | Which data object does a record belong to, and where does it live? |
| Mapping | [Mapping Configuration](./05_Mapping_Configuration/README.md) | Which source field ends up in which data object field? |
| Processing Settings | [Processing Settings](./06_Processing_Settings.md) | How is the import processed? |
| Execution tab | [Execution Configuration](./07_Execution_Configuration.md) | When does the import run? |

To understand what happens after an import is started, read
[Import Execution Details](../04_Import_Execution_Details.md).
