# Configuration Validation and Schema Services

Services for validating Data Importer configurations and providing schema metadata for AI agents.

## Overview

- **ConfigurationValidationService** - Validates configurations using Symfony TreeBuilder
- **ConfigurationSchemaService** - Generates JSON schemas for AI agents and tools
- **ConfigurationDefinition** - Single source of truth for all configuration structures
- **SchemaAwareInterface** - Services provide their own schema metadata

> 📘 See [Schema-Aware Services Guide](../Settings/SCHEMA_AWARE_GUIDE.md) for custom service development.

## Configuration Structure

```yaml
pimcore_data_hub:
  configurations:
    my-import:
      general:              # General settings
      loaderConfig:         # Data source
      interpreterConfig:    # Data format
      resolverConfig:       # Element resolution
      processingConfig:     # Processing options
      mappingConfig:        # Field mappings
      executionConfig:      # Scheduling
```

## Validating Configurations

### Programmatic

```php
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;

$validationService = \Pimcore::getContainer()->get(ConfigurationValidationService::class);
$result = $validationService->validateConfiguration($configuration);

if ($result->isValid()) {
    echo "Valid!\n";
} else {
    foreach ($result->getErrors() as $error) {
        echo "{$error->getPath()}: {$error->getMessage()}\n";
    }
}
```

### Command Line

```bash
# Validate configuration
bin/console datahub:data-importer:validate-config my-import-config

# JSON output
bin/console datahub:data-importer:validate-config my-import-config --json
```

## Getting Schema Information

### Programmatic

```php
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;

$schemaService = \Pimcore::getContainer()->get(ConfigurationSchemaService::class);

// Complete schema
$schema = $schemaService->getCompleteSchema();

// Section-specific
$loaderSchema = $schemaService->getLoaderConfigSchema();
```

### Command Line

```bash
# Complete schema
bin/console datahub:data-importer:validate-config --schema

# Specific section
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig

# JSON for AI agents
bin/console datahub:data-importer:validate-config --schema --json
```

### Schema Output Example

```json
{
  "loaderConfig": {
    "description": "Configuration for data loading",
    "required": ["type"],
    "properties": {
      "type": {
        "type": "string",
        "enum": ["asset", "http", "sftp", "upload", "push", "sql"]
      }
    },
    "availableTypes": {
      "asset": {
        "type": "asset",
        "class": "Pimcore\\Bundle\\DataImporterBundle\\DataSource\\Loader\\AssetLoader",
        "description": "Load data from a Pimcore asset",
        "settings": {
          "assetPath": {
            "type": "string",
            "description": "Path to the asset in Pimcore",
            "required": true
          }
        }
      }
    }
  }
}
```

## Extending with Custom Services

### Register Service

```yaml
services:
  App\DataImporter\Loader\CustomLoader:
    tags:
      - { name: pimcore.datahub.data_importer.loader, type: custom }
```

### Available Tags

- `pimcore.datahub.data_importer.loader`
- `pimcore.datahub.data_importer.interpreter`
- `pimcore.datahub.data_importer.resolver.load`
- `pimcore.datahub.data_importer.resolver.location`
- `pimcore.datahub.data_importer.resolver.publish`
- `pimcore.datahub.data_importer.operator`
- `pimcore.datahub.data_importer.data_target`
- `pimcore.datahub.data_importer.cleanup`

Custom services are automatically:
1. Available in validation
2. Included in schema
3. Discoverable by AI agents

## Validation Process

The service validates in this order:

1. General configuration
2. Loader configuration
3. Interpreter configuration
4. Resolver strategies (load, location, publish)
5. Processing configuration
6. Mapping items with operators
7. Execution configuration

Each step attempts to instantiate the actual service to ensure:
- Required settings are present
- Setting values are valid
- Component dependencies are satisfied
- Service types are registered

## ValidationResult API

```php
$result->isValid();              // bool
$result->getErrors();            // ValidationError[]
$result->getWarnings();          // ValidationError[]
$result->getErrorMessages();     // string[]
$result->getWarningMessages();   // string[]
$result->toArray();              // ['valid' => bool, 'errors' => [...]]
```

## ValidationError API

```php
$error->getPath();     // "loaderConfig.settings.assetPath"
$error->getMessage();  // "Empty asset path."
$error->toArray();     // ['path' => '...', 'message' => '...']
```

## Architecture Principles

**Single Source of Truth**: All configuration structure is defined in `ConfigurationDefinition` using Symfony TreeBuilder.

**Reusability**: Both validation and schema generation use the same TreeBuilder definitions.

**Consistency**: Changes to configuration automatically affect both validation and schema.

**Extensibility**: Services implementing `SchemaAwareInterface` provide their own configuration schema.

## For AI Agents

1. **Query schema first** to understand available options
2. **Build configurations incrementally**, validating each section
3. **Use exact type names** from schema
4. **Check required fields** indicated in schema
5. **Custom types appear automatically** alongside built-in types

## Performance Notes

- Validation attempts to instantiate all components (thorough but has overhead)
- Schema is built once per request
- Consider caching schema for repeated queries

## Security

- Validation can use `ignorePermissions` flag for system checks
- Schema service is public for controller/command access
- Validation does not execute imports, only checks structure
