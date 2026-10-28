---
title: Upgrade Notes
description: Breaking changes and migration steps per release.
---

# Upgrade Notes

## Upgrade to 2026.3.3

### Large XLSX Imports Read the Sheet in Row Windows, and Formula Cells Prefer Cached Results

- **A formula cell can now import a different value, on every XLSX import, including files below the threshold.**
  This is a change of value, not only of speed, and nothing in the import log announces it. Until now the
  interpreter read the sheet with `Worksheet::toArray()`, which **recalculates** every formula while reading. It
  now reads cell by cell and prefers the result **cached in the file** (`Cell::getOldCalculatedValue()`), which is
  the rule the import preview has always applied and is now the rule on both reads.
  Where the cached result is stale, the old import brought in the freshly recalculated value and the new one brings
  in the stale cached value instead. A file carries a stale cached result when its formulas were written
  programmatically, when the spreadsheet application had calculation set to manual, or when a generator wrote the
  formulas together with results it did not recompute. Volatile formulas (`TODAY()`, `NOW()`, `RAND()`) are stale by
  definition from the moment the file was saved.
  Before the first import after the upgrade, open and save such files once in a spreadsheet application with
  automatic calculation on, so the cached results match the formulas. A file whose formula cells carry no cached
  result at all is unaffected: those are calculated, and each one is logged - see the next entry.
- A formula cell without a cached result is calculated on the rows loaded at the time - every row below the
  threshold, the rows of the current window above it - and logged as a warning naming the sheet and the cell,
  visible in the Import Logs tab. At most 50 such cells are listed per import run, followed by one line stating
  how many further cells were affected.
- **An XLSX import no longer aborts on a formula phpspreadsheet cannot calculate; it continues with the cell
  empty.** Until now such a formula threw out of the interpreter and failed the whole import, so a file that used
  to fail now imports. The cell arrives empty, is named in the warning described above and counts toward the same
  50, so it is visible in the Import Logs tab rather than silent - but a row that previously stopped the run now
  reaches the queue with a gap in it. If an import of yours relied on that failure to catch a broken source file,
  check the import log for these warnings instead. Windowing is why this changed: a formula referencing rows
  outside the current window is routine, so what used to be a rare abort would otherwise have become a likely one.
  A formula that merely evaluates to `#NAME?` or `#VALUE!` is unaffected - those are values and were always
  imported as that text.
- Above `pimcore_data_importer.xlsx_interpreter.windowed_read_cell_threshold` cells (rows times columns, `200000`
  by default) the XLSX interpreter no longer loads the whole workbook before creating queue items. It reads the
  sheet in row windows, which lowers peak memory: a generated 100,000-row file of five columns measured 334 MB
  before and 117 MB after. A file at or below the threshold is loaded as a whole as before, so imports that already
  fit in memory keep the faster read. See [Installation](./README.md) for the setting.
- Interpreting an XLSX above the threshold takes several times longer, because each window reads the file again:
  interpreting the same 100,000-row file of five columns went from **9.3 s to 37.7 s of CPU time** (`getrusage()`,
  user plus system), a factor of four. These are CPU figures rather than wall clock on purpose - the wall clock of
  the same measurement varied by up to a factor of two between runs on one machine under other load, while its CPU
  time stayed within about a tenth, so the wall clock is not reproducible enough to quote. Expect the factor rather
  than the seconds, which depend on the machine; the memory figures, by contrast, reproduce exactly. The
  interpretation step only creates the queue items, so this shows up as a slower start of a large import, not as a
  slower import overall. Raise the threshold to keep a file on the faster read, lower it when an import exhausts
  the memory limit.
- Memory does not become flat above the threshold, it only drops: phpspreadsheet parses the whole sheet XML before
  a row window is applied to it, which for the 100,000-row file above costs 72 MB on its own.
- Below the threshold the file is still read in one pass, and it needs slightly less memory than before: 41 MB
  instead of 45 MB on a 10,000-row file, 306 MB instead of 334 MB on a 100,000-row one. What it costs instead is
  one extra pass over the file to read the sheet dimensions, not the per-cell formula rule. That pass is a fixed
  cost, so measured against the pre-change read it is no measurable extra time on a 100,000-row file and about a
  third more on a 10,000-row one - a fraction of a second either way, since that file interprets in about one.
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
