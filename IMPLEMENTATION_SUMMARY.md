# Data Importer Configuration Validation - Implementation Summary

## What Was Created

### Core Services

1. **ConfigurationValidationService** (`src/Validation/ConfigurationValidationService.php`)
   - Validates complete Data Importer configurations
   - Uses existing factories to attempt component instantiation
   - Returns structured ValidationResult with errors and warnings
   - Validates all configuration sections: loader, interpreter, resolver, processing, mapping, execution

2. **ConfigurationSchemaService** (`src/Validation/Schema/ConfigurationSchemaService.php`)
   - Provides complete schema information for AI agents
   - Introspects all registered tagged services
   - Returns metadata about available types, settings, constraints
   - Supports section-specific schema queries

3. **ValidationResult** (`src/Validation/ValidationResult.php`)
   - Encapsulates validation results
   - Provides convenience methods for error/warning access
   - Supports JSON serialization

4. **ValidationError** (`src/Validation/ValidationError.php`)
   - Represents individual validation errors
   - Includes path and message for precise error location

### Infrastructure

5. **ConfigurationSchemaServicePass** (`src/DependencyInjection/Compiler/ConfigurationSchemaServicePass.php`)
   - Compiler pass that collects all tagged services
   - Creates ServiceLocators for schema service access
   - Supports 8 different service categories

6. **ValidateConfigurationCommand** (`src/Command/ValidateConfigurationCommand.php`)
   - CLI command for validation and schema inspection
   - Supports JSON output for automation
   - Interactive schema browsing

### Integration

7. **Service Registration** (updated `src/Resources/config/services.yml`)
   - Registered validation and schema services
   - ConfigurationSchemaService marked as public for access

8. **Bundle Registration** (updated `src/PimcoreDataImporterBundle.php`)
   - Added compiler pass to bundle build

9. **Documentation** (`src/Validation/README.md`)
   - Comprehensive usage guide
   - Examples for developers and AI agents
   - Extension documentation

## How It Works

### Architecture

```
Configuration (YAML/Array)
         ↓
ConfigurationValidationService
         ↓
    (validates each section using existing factories)
         ↓
  DataLoaderFactory
  InterpreterFactory
  ResolverFactory
  MappingConfigurationFactory
  CleanupStrategyFactory
         ↓
    ValidationResult
    (errors + warnings)
```

### Extensibility

The system automatically discovers extensions through Symfony service tags:

```yaml
# Custom service registration
services:
  App\CustomLoader:
    tags:
      - { name: "pimcore.datahub.data_importer.loader", type: "custom" }
```

The ConfigurationSchemaServicePass collects all tagged services at compile time and makes them available through ServiceLocators.

### Validation Strategy

The validation service leverages the existing factory pattern:
1. Prepares configuration with defaults
2. For each section, attempts to instantiate components using existing factories
3. Catches InvalidConfigurationException and other errors
4. Builds structured error report with paths

This approach ensures:
- Validation logic stays in sync with actual component requirements
- Each component's `setSettings()` method performs its own validation
- No duplication of validation logic

## Usage Examples

### For Developers

```php
// Validate configuration
$validator = $container->get(ConfigurationValidationService::class);
$result = $validator->validateConfiguration($configArray);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        echo "{$error->getPath()}: {$error->getMessage()}\n";
    }
}
```

### For AI Agents

```bash
# Get schema as JSON
bin/console datahub:data-importer:validate-config --schema --json > schema.json

# AI can then:
# 1. Parse schema to understand available options
# 2. Generate configuration
# 3. Validate configuration before saving
bin/console datahub:data-importer:validate-config generated-config --json
```

### Schema Structure Example

```json
{
  "loaderConfig": {
    "description": "Configuration for data loading...",
    "availableTypes": {
      "asset": {
        "type": "asset",
        "description": "Load data from a Pimcore asset",
        "settings": {
          "assetPath": {
            "type": "string",
            "required": true,
            "description": "Path to the asset in Pimcore"
          }
        }
      }
    }
  }
}
```

## Tagged Service Categories

The system recognizes these tags (all with `type` attribute):

1. `pimcore.datahub.data_importer.loader` - Data source loaders
2. `pimcore.datahub.data_importer.interpreter` - File format interpreters
3. `pimcore.datahub.data_importer.resolver.load` - Element loading strategies
4. `pimcore.datahub.data_importer.resolver.location` - Location resolution strategies
5. `pimcore.datahub.data_importer.resolver.publish` - Publishing strategies
6. `pimcore.datahub.data_importer.operator` - Data transformation operators
7. `pimcore.datahub.data_importer.data_target` - Field mapping targets
8. `pimcore.datahub.data_importer.cleanup` - Cleanup strategies

## Benefits

### For Developers
- Programmatic configuration validation
- Clear error messages with paths
- Understanding of all available options
- Reduced trial-and-error configuration

### For AI Agents
- Machine-readable schema
- Automatic discovery of extensions
- Validation before persistence
- Complete configuration metadata

### For the System
- No duplication of validation logic
- Leverages existing factories
- Automatically includes extensions
- Consistent with runtime behavior

## Testing Recommendations

```bash
# Test with existing configuration
bin/console datahub:data-importer:validate-config car-import-clone

# Test schema access
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig

# Test with invalid configuration
# (modify a config to have errors, then validate)
```

## Next Steps / Potential Enhancements

1. **Web UI Integration** - Add validation to admin interface
2. **Configuration Builder** - Interactive wizard using schema
3. **Templates** - Pre-built configuration templates
4. **Import/Export** - Configuration sharing and versioning
5. **Real-time Validation** - Validate as user types in UI
6. **Detailed Settings Schemas** - More detailed operator/target settings metadata
7. **Dependency Validation** - Check field existence in target classes
8. **Data Preview** - Preview import with validation

## Files Created

- `src/Validation/ConfigurationValidationService.php` - Main validation service
- `src/Validation/ValidationResult.php` - Result object
- `src/Validation/ValidationError.php` - Error object
- `src/Validation/Schema/ConfigurationSchemaService.php` - Schema service
- `src/DependencyInjection/Compiler/ConfigurationSchemaServicePass.php` - Compiler pass
- `src/Command/ValidateConfigurationCommand.php` - CLI command
- `src/Validation/README.md` - Comprehensive documentation

## Files Modified

- `src/Resources/config/services.yml` - Service registration
- `src/PimcoreDataImporterBundle.php` - Compiler pass registration
