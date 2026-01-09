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
5. ✅ No lines exceed 120 characters
6. ✅ No duplicated string literals (use constants)
7. ✅ Method complexity ≤10, constructor parameters ≤5, method length ≤50 lines

## Code Quality Standards (SonarCloud/Quodana Compliance)

### Line Length Limits
**CRITICAL**: Maximum 120 characters per line for all code files.

**Techniques for splitting long lines:**

```php
// ❌ BAD: Long exception message (>120 chars)
throw new InvalidConfigurationException("Unsupported input type for quantity value operator with static unit at transformation position");

// ✅ GOOD: Split with string concatenation
throw new InvalidConfigurationException(
    "Unsupported input type for quantity value operator with static unit at " .
    "transformation position"
);

// ❌ BAD: Long method call
$quantityValue = $this->tester->grabService(\Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValue::class);

// ✅ GOOD: Split method call across lines
$quantityValue = $this->tester->grabService(
    \Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValue::class
);

// ❌ BAD: Long array declaration
$settings = ['unitSourceSelect' => 'static', 'staticUnitSelect' => '1', 'unitNullIfNoValueCheckbox' => true];

// ✅ GOOD: Multi-line array
$settings = [
    'unitSourceSelect' => 'static',
    'staticUnitSelect' => '1',
    'unitNullIfNoValueCheckbox' => true
];

// ❌ BAD: Long condition
if ($inputType === TransformationDataTypeService::ARRAY && $settings['arrayMode'] === 'single' && $unitSource === 'static') {

// ✅ GOOD: Split condition with logical operators on separate lines
if ($inputType === TransformationDataTypeService::ARRAY 
    && $settings['arrayMode'] === 'single' 
    && $unitSource === 'static'
) {

// ❌ BAD: Long sprintf with multiple parameters
throw new \Exception(sprintf('Failed to load data from %s with parameters %s using method %s', $url, $params, $method));

// ✅ GOOD: Multi-line sprintf
throw new \Exception(sprintf(
    'Failed to load data from %s with parameters %s using method %s',
    $url,
    $params,
    $method
));
```

### String Literal Deduplication
**CRITICAL**: Never repeat string literals more than once - use constants.

```php
// ❌ BAD: Duplicated literals across multiple methods
if (!in_array($inputType, ['default', 'numeric'])) { /* ... */ }
if (!in_array($returnType, ['default', 'numeric'])) { /* ... */ }

// ✅ GOOD: Define constants
private const SUPPORTED_TYPES = ['default', 'numeric'];

if (!in_array($inputType, self::SUPPORTED_TYPES)) { /* ... */ }
if (!in_array($returnType, self::SUPPORTED_TYPES)) { /* ... */ }

// ❌ BAD: Repeated validation message
throw new \Exception('Validation failed: invalid configuration');
// ... elsewhere in code
throw new \Exception('Validation failed: invalid configuration');

// ✅ GOOD: Use constant
private const MSG_VALIDATION_FAILED = 'Validation failed: invalid configuration';

throw new \Exception(self::MSG_VALIDATION_FAILED);
```

**Common constant naming patterns:**
- `MSG_*` for user-facing messages
- `ERR_*` for error messages  
- `INFO_*` for informational text
- `TYPE_*` for type identifiers
- `SUPPORTED_*` for allowed value lists

### Complexity Reduction

**Cyclomatic complexity limit**: Maximum 10 per method.

```php
// ❌ BAD: Complex method with many branches
public function process($value, $settings) {
    if ($settings['mode'] === 'A') {
        if ($value > 100) {
            // logic
        } elseif ($value < 0) {
            // logic  
        } else {
            // logic
        }
    } elseif ($settings['mode'] === 'B') {
        // more branches...
    }
    // 15+ decision points
}

// ✅ GOOD: Extract helper methods
public function process($value, $settings) {
    return match($settings['mode']) {
        'A' => $this->processModeA($value),
        'B' => $this->processModeB($value),
        default => $this->processDefault($value)
    };
}

private function processModeA($value) {
    // Focused logic for mode A
}
```

**Strategies to reduce complexity:**
- Extract helper methods (each doing one thing)
- Use early returns to reduce nesting
- Replace nested if/else with switch/match expressions
- Use strategy pattern for complex conditionals
- Limit method parameters to 5 maximum

### Constructor Parameter Limits
**Maximum 5 parameters per constructor** - use configuration objects or builder pattern for more.

```php
// ❌ BAD: Too many constructor parameters
public function __construct(
    private ServiceA $serviceA,
    private ServiceB $serviceB,
    private ServiceC $serviceC,
    private ServiceD $serviceD,
    private ServiceE $serviceE,
    private ServiceF $serviceF,
    private ServiceG $serviceG
) {}

// ✅ GOOD: Use service locator or wrapper
public function __construct(
    private ConfigurationServices $services
) {}

// Or extract to multiple focused classes
class ConfigurationValidator {
    public function __construct(
        private SchemaValidator $validator,
        private ConfigRepository $repository
    ) {}
}
```

### Method Length Limits
**Maximum 50 lines per method** - extract longer methods into smaller, focused ones.

```php
// ❌ BAD: 100+ line method doing multiple things
public function validateAndProcess($config) {
    // 30 lines of validation
    // 40 lines of transformation
    // 30 lines of persistence
}

// ✅ GOOD: Split into focused methods
public function validateAndProcess($config) {
    $this->validate($config);
    $transformed = $this->transform($config);
    $this->persist($transformed);
}

private function validate($config) { /* ... */ }
private function transform($config) { /* ... */ }
private function persist($config) { /* ... */ }
```

### Quick Validation Commands

```bash
# Check line lengths in PHP files
find . -name "*.php" -exec awk 'length > 120 {print FILENAME":"NR": (length "length") "$0}' {} \;

# Check for potential duplicate strings (review manually)
grep -r -h -o '"[^"]\{20,\}"' *.php | sort | uniq -c | sort -rn | head -20

# Run PHPStan for complexity analysis
vendor/bin/phpstan analyse --level=max src/
```

### Documentation Requirements

**Add docblocks for public methods when necessary:**
- Complex methods with non-obvious behavior
- Methods with multiple parameters requiring clarification
- Methods throwing exceptions that need documentation
- API methods consumed by external code

**Do not add @param docblocks when parameters are already typed** - type hints are sufficient.

```php
// ❌ BAD: Redundant @param for typed parameters
/**
 * @param string $name
 * @param int $age
 * @return User
 */
public function createUser(string $name, int $age): User

// ✅ GOOD: Only document non-obvious behavior or exceptions
/**
 * Creates a user account and sends welcome email.
 * 
 * @throws InvalidArgumentException When age is below minimum requirement
 */
public function createUser(string $name, int $age): User

// ✅ GOOD: Document complex types when helpful
/**
 * @param array<string, mixed> $settings Operator configuration with keys: 'mode', 'unitSource', 'staticUnit'
 * @throws InvalidConfigurationException When required settings are missing
 */
public function process($inputData, array $settings)
```

**For complex logic, add inline comments explaining WHY, not WHAT:**

```php
// ✅ GOOD: Explains reasoning
// Skip validation for legacy data imports to maintain backward compatibility
if ($settings['legacyMode'] !== true) {
    $this->validate($data);
}

// ❌ BAD: States the obvious
// Check if legacy mode is not true
if ($settings['legacyMode'] !== true) {
```
