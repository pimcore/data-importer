# Pimcore Data Importer Bundle - Development Guidelines

## Docker Environment

This project runs in a Docker container. All PHP commands must be executed inside the Docker container.

### Execute PHP Commands in Docker

```bash
# General syntax
docker compose exec -T php <command>

# Example: Run a PHP script
docker compose exec -T php php bin/console cache:clear

# Example: Run Composer commands
docker compose exec -T php composer install
```

**Important**: Always run commands from the project root directory (`/home/christian/sources/demo-enterprise-vscode`), not from the bundle directory.

## Code Quality Tools

### PHPStan (Static Analysis)

PHPStan Level 5 is configured for this bundle. Always run PHPStan before committing code.

```bash
# Run PHPStan analysis
cd /home/christian/sources/demo-enterprise-vscode
docker compose exec -T php vendor/bin/phpstan analyze \
  --configuration=/var/www/html/dev/pimcore/data-importer/phpstan.neon \
  --error-format=table \
  --no-progress
```

**Configuration files**:
- `phpstan.neon` - PHPStan configuration (Level 5)
- `phpstan-bootstrap.php` - Bootstrap file for PHPStan
- `phpstan-baseline.neon` - Baseline for existing issues

**All PHPStan errors must be fixed before committing code.**

### PHP CS Fixer (Code Formatting)

PHP CS Fixer automatically formats code according to project standards.

```bash
# Run PHP CS Fixer
cd /home/christian/sources/demo-enterprise-vscode
docker compose exec -T php /var/www/html/vendor/bin/php-cs-fixer fix \
  --config=/var/www/html/dev/pimcore/data-importer/.php-cs-fixer.dist.php \
  --verbose \
  --allow-risky=yes
```

**Configuration file**: `.php-cs-fixer.dist.php`

**Always run CS Fixer before committing code to ensure consistent formatting.**

## Coding Standards

### Strict Types Declaration

**REQUIRED**: All new PHP files must include the strict types declaration immediately after the license header:

```php
<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\YourNamespace;
```

**This is mandatory for all new files created.**

### File Structure

1. PHP opening tag (`<?php`)
2. License header (with blank line after)
3. `declare(strict_types=1);` (with blank line after)
4. Namespace declaration
5. Use statements (alphabetically ordered by PHP CS Fixer)
6. Class declaration

## Development Workflow

1. **Write code** following Symfony and Pimcore best practices
2. **Add `declare(strict_types=1);`** to all new files
3. **Run PHPStan** to check for type errors
4. **Fix all PHPStan errors**
5. **Run PHP CS Fixer** to format code
6. **Commit changes**

## Bundle-Specific Architecture

### Configuration System

The bundle uses a centralized configuration architecture with Symfony TreeBuilder:

#### ConfigurationDefinition (Single Source of Truth)
- `src/Settings/ConfigurationDefinition.php` - Central definition of all configuration structures
- Defines TreeBuilder definitions for all configuration sections (general, loader, interpreter, resolver, processing, mapping, execution)
- Used by both schema generation and validation services
- Eliminates duplicate structure knowledge across the codebase

#### Schema System
- Implement `SchemaAwareInterface` for services that have configurable settings (loaders, interpreters, strategies, etc.)
- Return `?TreeBuilder` from `getConfigTreeBuilder()` method
- Return `null` if the service has no configurable settings
- Schema is automatically converted to JSON Schema for AI agents via `TreeBuilderToJsonSchemaConverter`

#### Validation System
- `ConfigurationValidationService` - Validates configurations using TreeBuilder from ConfigurationDefinition
- `ConfigurationSchemaService` - Generates JSON schemas from ConfigurationDefinition + SchemaAwareInterface implementations
- Dual validation approach: schema validation + factory instantiation for runtime checks

**Architecture principle**: Configuration structure is defined once in ConfigurationDefinition, then reused by all services that need it. This ensures consistency and maintainability.

### Type Annotations

Always add PHPDoc type hints when PHPStan requires them:

```php
/** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
$rootNode = $treeBuilder->getRootNode();
```

## Testing

Tests run in a **separate Docker Compose environment** located in `tests/bin/docker-compose.yml`.

### One-time init + first run

```bash
cd /home/christian/sources/demo-enterprise-vscode/dev/pimcore/data-importer/tests/bin
bash init-tests.sh
```

This starts the dedicated test compose stack, sets up Pimcore for tests, installs deps, and runs Codeception once.

### Re-run tests (environment already up)

```bash
cd /home/christian/sources/demo-enterprise-vscode/dev/pimcore/data-importer/tests/bin
docker compose exec php-fpm vendor/bin/codecept run -vv
```

### Run specific test/class/method

```bash
docker compose exec php-fpm vendor/bin/codecept run tests/unit/ConfigurationSchemaServiceTest.php -vv
docker compose exec php-fpm vendor/bin/codecept run tests/unit/ConfigurationSchemaServiceTest.php:testResolverConfigSchema -vv
```

### ⚠️ Do NOT auto-clean the test stack

Never script automatic `docker compose down` for the test stack to avoid touching the main compose. When you are done, shut it down manually:

```bash
docker compose down -v --remove-orphans
```

### Writing tests

- Unit tests live in `tests/unit/` and extend `Codeception\Test\Unit`.
- Use `$this->tester->grabService(Foo::class)` to fetch services from the container.
- Call `TestHelper::cleanUp()` in `_after()` to reset state.
- Name files `*Test.php`; register helpers in `unit.suite.yml`.

### Pre-PR checklist

1. ✅ All new files have `declare(strict_types=1);`
2. ✅ PHPStan Level 5 passes with no errors
3. ✅ PHP CS Fixer has formatted all code
4. ✅ All tests (Codeception) pass in the test compose environment
