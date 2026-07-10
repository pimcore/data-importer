---
title: Studio Integration
description: How the configuration panel is built, how permissions work, and how to drive imports from outside Pimcore.
---

# Studio Integration

The Data Importer configuration panel is a Pimcore Studio UI plugin. The bundle ships the compiled frontend assets and a
set of backend endpoints that the panel calls.

## Permissions

Access is checked on two levels:

- **Gate permission** `plugin_datahub_config` ("Datahub Configuration"). Required to open the panel at all. It is shared
  with Datahub, so any user administering Datahub configurations already has it.
- **Per-configuration permissions** `read`, `update` and `delete`. Granted per user and role in the **Permissions** tab
  of an import configuration. A user holding the gate permission but lacking `read` on a specific configuration cannot
  open it.

Installing the bundle also creates the `plugin_datahub_adapter_dataImporterDataObject` permission
("Datahub Adapter - Data Object Importer"), which controls whether a user may use the Data Objects Importer adapter.

## The Studio API

The panel is backed by endpoints under `/pimcore-studio/api/bundle/data-importer`, covering configuration management,
preview data, transformation results, import execution, data type lookups and Doctrine connection listing.

:::warning

These endpoints are marked `@internal`. They exist to serve Pimcore Studio and may change in any release without a
deprecation path. Do not build integrations against them.

:::

Their current definitions, request and response schemas are generated from the code and served by the Studio Backend
bundle:

```
https://<your-pimcore-host>/pimcore-studio/api/docs
```

They are tagged `Bundle Data Importer` in that documentation. To modify a response before it is returned, listen for the
matching pre-response event rather than overriding the controller. See [Events](./02_Events.md).

## Driving Imports from Outside Pimcore

Use these supported interfaces instead of the internal API:

| Goal | Interface |
|---|---|
| Send data into Pimcore from another system | The [`Push` data source](../03_Configuration/01_Data_Sources.md#push) endpoint, authenticated with an API key. |
| Start an import from a deployment script or external scheduler | `bin/console datahub:data-importer:prepare-import <config_name>` |
| Process the import queue | `bin/console datahub:data-importer:process-queue-parallel` and `datahub:data-importer:process-queue-sequential`, or a Symfony Messenger worker. |
| React to imported elements | The [import events](./02_Events.md). |

## Extending the Panel

To add a data source, file format, operator or data target to the panel, register a dynamic type into the corresponding
Studio registry. See [Custom Strategies](./01_Custom_Strategies.md).
