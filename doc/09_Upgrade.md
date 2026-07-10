---
title: Upgrade Notes
description: Breaking changes and migration steps per release.
---

# Upgrade Notes

## Upgrade to 2026.1.0

### PHP & Symfony Version Support

- Added support for `PHP` `8.5`.
- Removed support for `PHP` `8.3` and Symfony `v6`.

### Removed Admin Classic / ExtJS UI

- Removed `pimcore/admin-ui-classic-bundle` dependency. The bundle no longer implements `PimcoreBundleAdminClassicInterface` or uses `BundleAdminClassicTrait`.
- Removed all ExtJS-based JavaScript and CSS paths (`getCssPaths()`, `getJsPaths()` methods removed from `PimcoreDataImporterBundle`).
- `PimcoreAdminBundle` is no longer registered as a dependent bundle.
- The Admin UI configuration panel is now implemented using the Pimcore Studio UI.
- `Pimcore\Bundle\AdminBundle\Helper\QueryParams` is no longer used; replaced by `DataTypeServiceInterface::extractSortingSettings()`.

### Studio UI

- Added Studio UI implementation for the Data Importer configuration panel (tabs: Data Setup, Execution, Import Logs).
- Studio frontend assets are now shipped with the bundle.

### Namespace Changes

- Changed namespace from `Pimcore\Log\ApplicationLogger` to `Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger`.

### Messenger Transport Configuration

- The messenger transport DSN is now configurable via the `%pimcore.messenger.transport_dsn_prefix%` container parameter instead of being hardcoded to `doctrine://default`. This allows the installer to wire the transport DSN from environment variables (e.g. `PIMCORE_MESSENGER_TRANSPORT_DSN_PREFIX`).

### Interface & Return Type Changes

- `DataTypeServiceInterface`: Added new method `extractSortingSettings(?string $sort): array` to the interface. Any custom implementations of `DataTypeServiceInterface` must implement this method.
- `CronValidationResponse::isValid(): bool` renamed to `CronValidationResponse::getIsValid(): bool`.
- `ImportProgressResponse::isRunning(): bool` renamed to `ImportProgressResponse::getIsRunning(): bool`.
- `PimcoreDataImporterBundle::getInstaller()` return type changed from `?InstallerInterface` to `InstallerInterface` (non-nullable).

### Class Visibility & Finalization

- Many classes have been marked `final` and/or `@internal`. Custom subclasses of these classes are no longer supported:
  - `CronScheduler`, `JobScheduler`, `SchedulerFactory`: marked `final` and `@internal`
  - `PreviewData`: marked `final` and `@internal`
  - `PimcoreDataImporterBundle`: marked `final`
  - Various exception classes (`InvalidScheduleException`, etc.): marked `final`
- Several `protected` methods on final classes have been changed to `private`. If you were overriding these methods in subclasses, you must refactor.
- `ConfigDataObjectController::saveAction()` return type changed from `?JsonResponse` to `JsonResponse` (non-nullable).
- `Installer::getLastMigrationVersionClassName()` return type changed from `?string` to `string` (non-nullable).

### Doctrine

- Removed Doctrine enum mapping from the bundle configuration.

### Dependency Updates

- `phpoffice/phpspreadsheet` version requirement bumped to `^4.3 || ^5.1` (dropped `^2.2 || ^3.3`).

### API Schema Changes

- `UnitDataResponse`: Schema property renamed from `UnitList` to `unitList` (camelCase). If you consume this API response, update your client accordingly.

## Update to Version 1.11

### General

- Added support of `doctrine/dbal` `v4`, dropped support of `doctrine/dbal` `v2`

## Update to Version 1.10

### General

- Dropped support of Pimcore 10, bumped minimum requirement of `pimcore/pimcore` to `^11.2`.
