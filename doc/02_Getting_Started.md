---
title: Getting Started
description: Build a first import configuration end to end, from data source to a running import.
---

# Getting Started

This page walks through a first import: a CSV file of products is mapped onto a `Product` data object class and imported.
Each step links to the reference chapter that explains every available option.

## Prerequisites

- The bundle is installed and a queue processing mode is set up, see [Installation](01_Installation/README.md).
- Your user has the `plugin_datahub_config` permission and the
  `plugin_datahub_adapter_dataImporterDataObject` permission.
- A data object class exists to import into. This example uses a class named `Product` with the fields `sku` and `name`.

## 1. Create an Import Configuration

In Pimcore Studio, open the main navigation and choose **Automation & Integration** > **Data Hub Configuration**.
Add a new configuration and select the **Data Objects Importer** adapter type.

The configuration opens with these tabs:

| Tab | Purpose |
|---|---|
| General | Activate the configuration, and set its name, description and group. |
| Data Setup | The import definition itself, split into five steps. |
| Execution | Schedule the import, start it manually, and watch its progress. |
| Import Logs | Application logger entries belonging to this configuration. |
| Permissions | Which users and roles may read, update and delete this configuration. |

The **Data Setup** tab drives the rest of this walkthrough. Work through its five steps from left to right.

## 2. Data Source

Choose where the import data comes from and how it is parsed. For this example, pick the **Upload** data source so you
can import a local file directly, and set the file format to **CSV**.

A CSV file such as this one:

```csv
sku,name
A-001,Racing Bike
A-002,Touring Bike
```

needs **Skip First Row** and **Save Header Name** enabled. Skipping the first row keeps the header out of the imported
data; saving the header name makes the columns addressable as `sku` and `name` instead of `0` and `1`.

Reference: [Data Sources](03_Configuration/01_Data_Sources.md) and [File Formats](03_Configuration/02_File_Formats.md).

## 3. Preview Import

Load a small extract of the data so the later steps can show real values. Either upload a preview file or copy one from
the configured data source. The preview shows one record at a time and never writes anything to Pimcore.

Reference: [Import Preview](03_Configuration/03_Import_Preview.md).

## 4. Resolver

The resolver decides which data object a row belongs to. Configure four things:

- **Data Object Class**: `Product`.
- **Element Loading**: how an existing object is found so it gets updated instead of duplicated. Use the `Attribute`
  strategy with the attribute `sku` and the data source index `sku`.
- **Element Creation**: where a new object is placed when no existing one is found. Use `Static Path` with
  `/Products`.
- **Element Publishing**: whether imported objects are published. `Always Publish` is a sensible start.

Reference: [Resolver Settings](03_Configuration/04_Resolver_Settings.md).

## 5. Mapping

A mapping entry connects one or more source columns to one data object field. Create two entries:

| Source | Data Target |
|---|---|
| `sku` | `sku` |
| `name` | `name` |

Both are direct one-to-one assignments, so no transformation is needed. When the source value does not fit the target
field, open **Advanced** and add operators to the transformation pipeline, for example `Trim` to strip whitespace or
`Numeric` to cast a string to a number.

Reference: [Mapping Configuration](03_Configuration/05_Mapping_Configuration/README.md).

## 6. Processing Settings

Leave the defaults for a first run. The one setting worth understanding now is **Execution Type**:

- **Sequential** processes rows one after another. Choose this when rows depend on each other, for example when the
  import builds a hierarchy.
- **Parallel** processes rows concurrently. Faster, but the order is not guaranteed.

Reference: [Processing Settings](03_Configuration/06_Processing_Settings.md).

## 7. Run the Import

Save the configuration, switch to the **Execution** tab, and select **Start Import**.

Starting an import does not import anything by itself. It prepares the import: the source file is loaded, split into
rows, and the rows are queued. The queue worker you configured during installation then processes those rows. The
**Execution Status** panel shows how many items have been processed and lets you cancel a running import.

If the status never moves past zero, queue processing is not running. See
[Troubleshooting / FAQ](08_Troubleshooting_FAQ.md).

Reference: [Execution Configuration](03_Configuration/07_Execution_Configuration.md) and
[Import Execution Details](04_Import_Execution_Details.md).

## 8. Check the Result

Open the **Import Logs** tab. It lists the application logger entries for this configuration: when the import started,
which elements were imported, and any errors. Each imported element links to the original source row as a file object.

Reference: [Import Progress and Logging](05_Import_Progress_and_Logging.md).

## Where to Go Next

- Automate the run with a cron expression or a one-time job in
  [Execution Configuration](03_Configuration/07_Execution_Configuration.md).
- Speed up recurring full imports with **Delta Check**, and remove obsolete objects with **Cleanup**, in
  [Processing Settings](03_Configuration/06_Processing_Settings.md).
- Add a data source, file format or operator of your own in [Extending](06_Extending/README.md).
