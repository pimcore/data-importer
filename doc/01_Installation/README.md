---
title: Installation
description: Install the Data Importer bundle and set up queue processing and scheduled imports.
---

# Installation

## Prerequisites

The bundle declares [Datahub](https://github.com/pimcore/data-hub), the Studio Backend bundle and the Studio UI bundle
as Composer dependencies. Composer pulls them in automatically, and the bundle registers Datahub, the Application Logger
bundle and the Flysystem bundle as dependent bundles, so they are loaded without manual bundle ordering.

Loading Datahub this way does not install it. Datahub's own installer creates the `plugin_datahub_config` permission
and the Datahub permission category that Data Importer depends on, so Datahub must be installed on its own, see step 3
below.

## Bundle Installation

1. Install the package:

```bash
composer require pimcore/data-importer
```

2. Enable the bundle in `config/bundles.php`:

```php
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
// ...

return [
    // ...
    PimcoreDataImporterBundle::class => ['all' => true],
    // ...
];
```

3. Install Datahub, if it is not installed yet. Enabling it as a dependent bundle only loads it, it does not create
   the `plugin_datahub_config` permission and the Datahub permission category that Data Importer depends on:

```bash
bin/console pimcore:bundle:install PimcoreDataHubBundle
```

4. Install the bundle:

```bash
bin/console pimcore:bundle:install PimcoreDataImporterBundle
```

The installer creates the `plugin_datahub_adapter_dataImporterDataObject` user permission in the Datahub permission
category. See [User Permissions](#user-permissions) below for what it controls and what else is required.

## User Permissions

Access to the configuration panel and to individual import configurations is checked on two independent levels.

### Gate Permission

`plugin_datahub_config` ("Datahub Configuration") is required to reach any endpoint of the configuration panel. It is
created by Datahub's installer and shared with Datahub, so a user administering Datahub configurations already has it.
Without it every request of the panel is rejected.

### Access to an Individual Configuration

Each configuration is then checked separately for `read`, `update` and `delete`. The rules are evaluated in this order:

1. A user with the `admin` flag, or with the `plugin_datahub_admin` ("Datahub Admin") permission, is granted
   everything.
2. If the configuration has **no** entries in its **Permissions** tab, the adapter permission
   `plugin_datahub_adapter_dataImporterDataObject` ("Datahub Adapter - Data Object Importer") decides. The installer
   creates this permission in the Datahub permission category.
3. As soon as the **Permissions** tab holds at least one user or role entry, the adapter permission is ignored for that
   configuration and only those entries apply. An entry matching the user's own name wins outright; otherwise the
   user's roles are checked and any role granting the operation is enough.

So the per-configuration grid replaces the adapter permission, it does not narrow it. Adding a single entry to a
configuration locks out every other non-admin user, including users who hold the adapter permission.

Grant the gate and adapter permissions to every user or role that works with import configurations. Use the
**Permissions** tab only when a configuration needs its own, self-contained access list.

## Queue Processing

Imports never run inside the request that starts them. An import first writes its rows into a queue, and a separate
worker processes that queue. Set up one of the two processing modes below, otherwise imports stay queued and the
execution status never progresses.

For the difference between sequential and parallel processing, see
[Import Execution Details](../04_Import_Execution_Details.md).

### Command-based Processing

Run both commands on a regular basis. The interval depends on the use case and the system environment.

```bash
# Process queue items that can run in parallel
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-parallel --processes=5
# Process queue items that must run one after another
*/5 * * * * php /home/project/www/bin/console datahub:data-importer:process-queue-sequential
```

### Symfony Messenger-based Processing

Activate messenger processing in the Symfony configuration:

```yml
pimcore_data_importer:
    messenger_queue_processing:
        activated: true
```

Queue processing then starts automatically as soon as an import is prepared. Messages are dispatched via the
`pimcore_data_import` transport, so run a worker for that transport:

```bash
bin/console messenger:consume pimcore_data_import
```

These optional settings tune the messenger processing:

| Setting | Default | Description |
|---|---|---|
| `worker_count_parallel` | `3` | Maximum number of parallel worker messages for parallel imports. |
| `worker_item_count` | `200` | Number of items imported per worker message. |
| `worker_count_lifetime` | `1800` | Lifetime in seconds of the tmp store entry holding the current worker count. After it expires, the value is cleared. |

## Scheduled Imports

An import configuration can run on a cron expression or at a fixed date and time. The
`datahub:data-importer:execute-cron` command evaluates both schedule types, so run it regularly. The shorter the
interval, the more accurately imports start at their scheduled time.

```bash
# Check schedules and start due imports
* * * * * php /home/project/www/bin/console datahub:data-importer:execute-cron
```

See [Execution Configuration](../03_Configuration/07_Execution_Configuration.md) for the schedule types.

## Next Steps

Follow [Getting Started](../02_Getting_Started.md) to build a first import configuration end to end.
