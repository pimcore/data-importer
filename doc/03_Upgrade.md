# Update Notes

## Upgrade to 2026.1.0
- The messenger transport DSN is now configurable via the `%pimcore.messenger.transport_dsn%` container parameter instead of being hardcoded to `doctrine://default`. This allows the installer to wire the transport DSN from environment variables (e.g. `PIMCORE_MESSENGER_TRANSPORT_DSN`).
- Added support to `PHP` `8.5`.
- Removed support to `PHP` `8.3` and Symfony `v6`.
- Change namespave from `Pimcore\Log\ApplicationLogger` to `Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger`

## Update to Version 1.11

### General

- Added support of `doctrine/dbal` `v4`, dropped support of `doctrine/dbal` `v2`

## Update to Version 1.10

### General

- Dropped support of Pimcore 10, bumped minimum requirement of `pimcore/pimcore` to `^11.2`.
