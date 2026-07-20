---
title: Troubleshooting / FAQ
description: Common problems when running imports, and how to resolve them.
---

# Troubleshooting / FAQ

## The execution status does not progress

![Execution Status](./img/execution.png)

The import was prepared and the records are queued, but no worker processes them. Set up either command-based or
Symfony Messenger-based queue processing, as described in
[Installation](01_Installation/README.md#queue-processing).

Check that the worker covers the execution type the configuration uses. A configuration set to `Parallel` is not
processed by `datahub:data-importer:process-queue-sequential`, and the other way around. See
[Import Execution Details](04_Import_Execution_Details.md).

## "ERROR: The command is already running." when processing the queue

A worker process terminated unexpectedly and left its lock behind.

Open the `lock_keys` table. The `key` column holds the SHA256 hash of the command name, for example of
`datahub:data-importer:process-queue-sequential`. Delete the row for the affected command and run it again.

## A scheduled import never starts

Cron and one-time schedules are evaluated by `datahub:data-importer:execute-cron`. If that command is not run regularly,
no scheduled import starts. See [Installation](01_Installation/README.md#scheduled-imports).

A one-time job is also skipped when the configuration was saved after the scheduled time, and it never runs a second
time.

## An import creates duplicates instead of updating objects

The element loading strategy did not find the existing object. Check in
[Resolver Settings](03_Configuration/04_Resolver_Settings.md):

- The **Data Source Index** points at the field that actually carries the identifier.
- With the `Attribute` strategy, the existing objects are published, or **Include unpublished objects** is enabled.
  Unpublished objects are not matched by default.

## An import reports that the preview file is invalid

The preview file does not parse with the configured file format, usually after the format was changed. Re-upload or
re-copy the preview data. See [Import Preview](03_Configuration/03_Import_Preview.md).

## Pushing data returns an error while the queue is filled

An import only prepares when the queue of that configuration is empty. Either let the running import finish, or enable
**Ignore Not Empty Queue** on the [`Push` data source](03_Configuration/01_Data_Sources.md#push).
