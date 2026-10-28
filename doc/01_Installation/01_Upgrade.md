---
title: Upgrade Notes
description: Breaking changes and migration steps per release.
---

# Upgrade Notes

## Upgrade to 2026.3.3

### XLSX Imports Read the Sheet in Row Windows and Prefer Cached Formula Results

- The XLSX interpreter no longer loads the whole workbook before creating queue items. It reads the sheet in row
  windows, which lowers peak memory: a generated 100,000-row file measured 334 MB before and 116 MB after. Memory
  still grows with the number of rows, because phpspreadsheet parses the whole sheet for every window. Every XLSX
  import takes this path; there is no size or row threshold and no fallback to the previous behaviour.
- Interpreting a large XLSX takes longer, because each window reads the file again: interpreting the same
  100,000-row file took about seven times as long. The interpretation step only creates the queue items, so this
  shows up as a slower start of a large import, not as a slower import overall.
- A formula cell now resolves to the result cached in the file, the same rule the import preview already applied.
  Imports of workbooks whose cached results are stale, or whose formulas are volatile (`TODAY()`, `NOW()`, `RAND()`),
  therefore import different values than before. Recalculate and save such files once before the next import if the
  cached results must not be used.
- A formula cell without a cached result is calculated on the loaded rows only and logged as a warning naming the
  sheet and the cell, visible in the Import Logs tab. At most 50 such cells are listed per import run, followed by
  one line stating how many further cells were affected.
- An XLSX file whose sheet holds no data no longer produces a single queue item of empty fields. Such a file now
  creates no queue items at all.
- An import whose configured sheet name is absent from the file now fails with the same
  `InvalidConfigurationException` the preview already raised, instead of the raw phpspreadsheet exception. The
  import failed before as well, only the message differs.
- `PreviewRowsReadFilter` was renamed to `RowsReadFilter`, because both the preview and the import use it now. The
  class is `@internal` and `final`.

### XML Values Keep Their Text When Typing Would Change Them

- The XML interpreter no longer converts a numeric looking value to a number when that would change it. `603E709`
  used to become `INF` and aborted the import, `0123` was stored as `83`. Such values now stay text.
- Values that stay text now: `0123` (was `83`), `12.50` (was `12.5`), `1.0`, `+5`, `1E5`, `0x1F`, `0b101`.
  Plain values such as `42`, `-7`, `1.5`, `true`, `false` and `null` are converted as before.
- If a column with such values is used as identifier (resolver, cleanup) or in a delta check, review it before the
  first import after the upgrade: the resolver may not find objects stored under the old value (duplicates), cleanup
  may unpublish or remove them, and the affected rows are imported again once.
- Operators that rely on a typed value answer differently for these inputs: the Boolean operator gave `true` for
  `1.0` and now gives `false`, the Numeric operator returns `0.0` for `0x1F` (was `31`).

## Upgrade to 2026.3.0

### Skipping Element Persistence From a `PreSaveEvent` Listener

- `DataObject\PreSaveEvent` now carries a skip flag: a listener can call `setSkipSave(true)` to stop the import
  from persisting the current element. The element is not saved, `DataObject\PostSaveEvent` is not dispatched, and
  the skip is written to the import log. Processing continues with the next record.
- A skipped element that already exists is reloaded afterwards, so the changes the mapping applied to it in
  memory cannot leak into a later row that resolves the same element.
- The flag defaults to `false`, so imports without such a listener behave exactly as before. No migration needed.

## Upgrade to 2026.2.6

### Frontend Build Ships as a Packaged Archive

- The compiled Studio frontend is no longer committed as an expanded `src/Resources/public/studio/build/`
  directory. It now ships as a single archive (`build-dist/build-<id>.zip`) that is extracted into
  `src/Resources/public/studio/build/` automatically during cache warmup.
- The extraction is provided by `pimcore/studio-ui-bundle`, which this bundle already requires at
  `^2026.2.6`. No dependency change is needed.
- Read-only filesystem deployments must run `bin/console cache:warmup` (or `cache:clear`) during the
  build/deploy phase, while the bundle directory (usually below `vendor/`) is still writable. Standard
  Pimcore deployments already do this.
- When `assets:install` runs in copy mode, run `cache:warmup` before it, otherwise no frontend assets are
  copied. If the filesystem becomes read-only before the first warmup, the bundle throws
  `BuildArchiveNotWritableException`, because there is no build to serve.

## Upgrade to 2026.1.0

### PHP & Symfony Version Support

- Added support for `PHP` `8.5`.
- Removed support for `PHP` `8.3` and Symfony `v6`.

### Removed Admin Classic / ExtJS UI

- Removed the `pimcore/admin-ui-classic-bundle` dependency. The bundle no longer implements
  `PimcoreBundleAdminClassicInterface` and no longer uses `BundleAdminClassicTrait`.
- Removed all ExtJS-based JavaScript and CSS paths. `getCssPaths()` and `getJsPaths()` were removed from
  `PimcoreDataImporterBundle`.
- `PimcoreAdminBundle` is no longer registered as a dependent bundle.
- The configuration panel is now implemented in Pimcore Studio.
- `Pimcore\Bundle\AdminBundle\Helper\QueryParams` is no longer used. It was replaced by
  `DataTypeServiceInterface::extractSortingSettings()`.

### Pimcore Studio

- Added the Pimcore Studio implementation of the Data Importer configuration panel, with the tabs Data Setup,
  Execution and Import Logs.
- Studio frontend assets are now shipped with the bundle.

### Namespace Changes

- Changed namespace from `Pimcore\Log\ApplicationLogger` to `Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger`.

### Messenger Transport Configuration

- The messenger transport DSN is configurable via the `%pimcore.messenger.transport_dsn_prefix%` container parameter
  instead of being hardcoded to `doctrine://default`. The installer can now wire the transport DSN from environment
  variables such as `PIMCORE_MESSENGER_TRANSPORT_DSN_PREFIX`.

### Interface & Return Type Changes

- `DataTypeServiceInterface`: added the method `extractSortingSettings(?string $sort): array`. Custom implementations
  of the interface must implement it.
- `CronValidationResponse::isValid(): bool` renamed to `CronValidationResponse::getIsValid(): bool`.
- `ImportProgressResponse::isRunning(): bool` renamed to `ImportProgressResponse::getIsRunning(): bool`.
- `PimcoreDataImporterBundle::getInstaller()` return type changed from `?InstallerInterface` to
  `InstallerInterface` (non-nullable).

### Class Visibility & Finalization

- Many classes have been marked `final` and/or `@internal`. Custom subclasses of these classes are no longer supported:
  - `CronScheduler`, `JobScheduler`, `SchedulerFactory`: marked `final` and `@internal`
  - `PreviewData`: marked `final` and `@internal`
  - `PimcoreDataImporterBundle`: marked `final`
  - Various exception classes (`InvalidScheduleException`, etc.): marked `final`
- Several `protected` methods on final classes were changed to `private`. Subclasses overriding them have to be
  refactored.
- `ConfigDataObjectController::saveAction()` return type changed from `?JsonResponse` to `JsonResponse` (non-nullable).
- `Installer::getLastMigrationVersionClassName()` return type changed from `?string` to `string` (non-nullable).

### Doctrine

- Removed Doctrine enum mapping from the bundle configuration.

### Dependency Updates

- `phpoffice/phpspreadsheet` version requirement bumped to `^4.3 || ^5.1` (dropped `^2.2 || ^3.3`).

### API Schema Changes

- `UnitDataResponse`: the schema property was renamed from `UnitList` to `unitList`. Update clients consuming this
  response.

## Update to Version 1.11

### General

- Added support of `doctrine/dbal` `v4`, dropped support of `doctrine/dbal` `v2`

## Update to Version 1.10

### General

- Dropped support of Pimcore 10, bumped minimum requirement of `pimcore/pimcore` to `^11.2`.
