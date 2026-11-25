---
title: Upgrade Notes
description: Breaking changes and migration steps per release.
---

# Upgrade Notes

## Upgrade to 2026.4.0

- Added: the `As Numeric Range`, `Quantity Value Range` and `As External Image` operators, and a
  `languagemultiselect` field or key is now offered for an `array` result type.
- [Classification Store] Changed: the key picker offers no key for a result type no key type can hold, where it used
  to offer every key in the store for `dateArray`, `quantityValueArray` and `inputQuantityValueArray`.
- [Classification Store] Added: a key of either advanced relation type can be imported, offered for `assetArray` and
  `dataObjectArray`, and its value is written as relation metadata.
- [Classification Store] Added: `Write If Source Is Empty` on both classification store data targets — off by default
  on `Classification Store Batch`, on by default on `Classification Store`. It takes effect once the entry's target
  type is re-selected or the control is changed.
- [Classification Store] A `quantityValue` or `inputQuantityValue` key in a plain `array` entry of
  `Classification Store Batch` is written as a quantity value object instead of the raw source value, so such a key
  now imports.
- [Classification Store] Changed: a mapping entry's key reference is saved as identifiers, and a key an entry cannot
  build is warned about instead of silently dropped.
- [Classification Store] A key reference that is neither `<GROUP_IDENTIFIER>:<KEY_IDENTIFIER>` nor two whole integers
  now fails the entry instead of addressing a key it never named — `1-2-3` and `1.5-2` both used to import into
  group 1 key 2.
- [Classification Store] A mapping entry writing to a non-localized classification store field no longer fails with a
  `TypeError`; it writes to the default language, unless the key itself is localized.
- [Classification Store] `Classification Store Batch` builds the value of every key type the store can hold from its
  JSON form, so the media, geo, relation, range, `link`, `structuredTable`, `video` and `dateRange` key types now
  import, and a token may name a variant as `<KEY>#<VARIANT>`. A key whose type the engine behind the store cannot
  hold is warned about and skipped instead of failing the whole row.
- [Classification Store] Changed: both data targets write a key under the configured language where the key is
  localized and without one where it is not, as the object editor reads it. The **Language** control follows the
  keys' own flags rather than the field's.

### Large XLSX Imports Read the Sheet in Row Windows, and Formula Cells Prefer Cached Results

- **A formula cell can import a different value than before, on every XLSX import.** The interpreter now prefers the
  result cached in the file, as the preview always has, instead of recalculating it. Where the cached result is
  stale, the stale value is imported.
- Open and save affected files once with automatic calculation on before the first import. Volatile formulas
  (`TODAY()`, `NOW()`, `RAND()`) are stale from the moment the file was saved.
- A formula cell with no cached result is calculated and logged as a warning naming the sheet and the cell, in the
  Import Logs tab. At most 50 are listed per run, then a count of the rest.
- **Cleanup is skipped for a run that imported such a cell empty, and above the threshold for any run that had to
  calculate one** — even with cleanup on. The row can reach the queue under an identifier the source does not hold,
  and cleanup would delete the element that identifier belongs to. The run imports as usual and logs the reason.
- A run below the threshold whose formulas all resolved cleans up as before, so do not rely on an uncached formula
  to protect existing elements.
- **An XLSX import no longer aborts on a formula phpspreadsheet cannot calculate**; the cell arrives empty and is
  named in that warning. A file that used to fail now imports, so check the import log rather than relying on the
  failure.
- Above `pimcore_data_importer.xlsx_interpreter.windowed_read_cell_threshold` cells (rows × columns, `200000` by
  default) the interpreter reads the sheet in row windows instead of loading the whole workbook. Peak memory on a
  100,000-row, five-column file went from 334 MB to 117 MB. See [Installation](./README.md).
- Interpreting above the threshold costs about four times the CPU time on that file (9.3 s to 37.7 s), because each
  window reads the file again. It is the queue-item step, so a large import starts slower rather than running
  slower. Raise the threshold to keep a file on the faster read, lower it when an import exhausts memory.
- Memory above the threshold drops rather than flattens: the sheet XML is parsed in full before a window is applied
  to it, 72 MB on that file.
- Below the threshold the file is still read in one pass and needs slightly less memory than before, at the cost of
  one extra pass to read the sheet dimensions.
- An XLSX file whose sheet holds no data no longer produces a single queue item of empty fields; it creates none.
- An import whose configured sheet name is absent now fails with the same `InvalidConfigurationException` the
  preview raised, instead of the raw phpspreadsheet exception.
- `PreviewRowsReadFilter` was renamed to `RowsReadFilter`, because both the preview and the import use it. The class
  is `@internal` and `final`.

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
