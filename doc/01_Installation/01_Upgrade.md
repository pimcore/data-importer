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
