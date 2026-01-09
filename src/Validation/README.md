# Data Importer Configuration Validation and Schema Services

This directory contains services for validating Data Importer configurations and providing schema information for AI agents and automated configuration tools.

## Overview

The Pimcore Data Importer uses a complex, hierarchical configuration structure with multiple subsections, each supporting various types and strategies. The validation and schema services provide:

1. **Configuration Validation** - Validates complete configurations by attempting to instantiate all components
2. **Schema Information** - Provides detailed metadata about all available configuration options
3. **Extensibility Support** - Works with tagged Symfony services, supporting custom extensions
4. **Self-Documenting Services** - Services can provide their own schema via `SchemaAwareInterface`

> 📘 **For Custom Service Development**: See [Schema-Aware Services Guide](../Settings/SCHEMA_AWARE_GUIDE.md) to learn how to make your custom services provide schema information for AI agents.

## Architecture

### Key Components

- **ConfigurationDefinition** - Centralized definition of all configuration structures using Symfony TreeBuilder (single source of truth)
- **ConfigurationValidationService** - Main validation service that validates configurations using TreeBuilder from ConfigurationDefinition
- **ConfigurationSchemaService** - Generates JSON schemas from ConfigurationDefinition + SchemaAwareInterface implementations
- **ValidationResult** - Encapsulates validation results with errors and warnings
- **ValidationError** - Represents individual validation errors with path and message
- **TreeBuilderToJsonSchemaConverter** - Converts Symfony TreeBuilder definitions to JSON Schema format
- **ConfigurationSchemaServicePass** - Compiler pass that collects all tagged services

### Architecture Principles

The validation and schema system follows a **centralized configuration approach**:

1. **Single Source of Truth**: All configuration structure (required fields, types, defaults, validation rules) is defined in `ConfigurationDefinition` class
2. **Reusability**: Both validation and schema generation use the same TreeBuilder definitions from ConfigurationDefinition
3. **Consistency**: Changes to configuration structure are automatically reflected in both validation and schema
4. **Extensibility**: Services implementing `SchemaAwareInterface` provide their own TreeBuilder for service-specific settings

### Configuration Structure

```yaml
pimcore_data_hub:
  configurations:
    my-import:
      general:              # General settings (name, type, active, etc.)
      loaderConfig:         # Data source configuration
      interpreterConfig:    # Data format interpretation
      resolverConfig:       # Element resolution and creation
      processingConfig:     # Processing and cleanup options
      mappingConfig:        # Field mapping and transformations
      executionConfig:      # Scheduling configuration
```

## Usage

### Validating Configurations

#### Programmatic Validation

```php
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;

// Get the service
$validationService = \Pimcore::getContainer()->get(ConfigurationValidationService::class);

// Validate a configuration array
$configuration = [
    'general' => [
        'name' => 'my-import',
        'type' => 'dataImporterDataObject',
        'active' => true,
    ],
    'loaderConfig' => [
        'type' => 'asset',
        'settings' => [
            'assetPath' => '/Import/data.csv'
        ]
    ],
    // ... rest of configuration
];

$result = $validationService->validateConfiguration($configuration);

if ($result->isValid()) {
    echo "Configuration is valid!\n";
} else {
    foreach ($result->getErrors() as $error) {
        echo "{$error->getPath()}: {$error->getMessage()}\n";
    }
}
```

#### Command Line Validation

```bash
# Validate a specific configuration
bin/console datahub:data-importer:validate-config my-import-config

# Output as JSON
bin/console datahub:data-importer:validate-config my-import-config --json
```

### Getting Schema Information

#### Complete Schema

```php
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;

$schemaService = \Pimcore::getContainer()->get(ConfigurationSchemaService::class);

// Get complete schema
$completeSchema = $schemaService->getCompleteSchema();
```

#### Section-Specific Schema

```php
// Get schema for specific sections
$loaderSchema = $schemaService->getLoaderConfigSchema();
$interpreterSchema = $schemaService->getInterpreterConfigSchema();
$resolverSchema = $schemaService->getResolverConfigSchema();
$processingSchema = $schemaService->getProcessingConfigSchema();
$mappingSchema = $schemaService->getMappingConfigSchema();
$executionSchema = $schemaService->getExecutionConfigSchema();
```

#### Command Line Schema Access

```bash
# Show complete schema
bin/console datahub:data-importer:validate-config --schema

# Show specific section schema
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig

# Output as JSON for AI agents
bin/console datahub:data-importer:validate-config --schema --json
```

### AI Agent Integration

The schema service is specifically designed to provide information that AI agents need to create valid configurations:

```json
{
  "loaderConfig": {
    "description": "Configuration for data loading...",
    "required": ["type"],
    "properties": {
      "type": {
        "type": "string",
        "required": true,
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

## Extending the Services

### Adding Custom Loaders

```yaml
# services.yaml
services:
  App\DataImporter\Loader\CustomLoader:
    tags:
      - { name: "pimcore.datahub.data_importer.loader", type: "custom" }
```

The custom loader will automatically be:
1. Available in the validation service
2. Included in schema information
3. Discoverable by AI agents

### Adding Custom Operators

```yaml
services:
  App\DataImporter\Operator\CustomOperator:
    tags:
      - { name: "pimcore.datahub.data_importer.operator", type: "customTransform" }
```

## Service Tags

The system recognizes these Symfony service tags:

- `pimcore.datahub.data_importer.loader` - Data loaders
- `pimcore.datahub.data_importer.interpreter` - Data interpreters
- `pimcore.datahub.data_importer.resolver.load` - Loading strategies
- `pimcore.datahub.data_importer.resolver.location` - Location strategies
- `pimcore.datahub.data_importer.resolver.publish` - Publishing strategies
- `pimcore.datahub.data_importer.operator` - Transformation operators
- `pimcore.datahub.data_importer.data_target` - Data targets
- `pimcore.datahub.data_importer.cleanup` - Cleanup strategies

Each tag requires a `type` attribute that becomes the configuration key.

## Validation Process

The validation service validates configurations in this order:

1. **General Configuration** - Basic structure and required fields
2. **Loader Configuration** - Validates loader type and attempts instantiation
3. **Interpreter Configuration** - Validates interpreter type and settings
4. **Resolver Configuration** - Validates all resolver strategies (load, location, publish)
5. **Processing Configuration** - Validates execution type and cleanup strategy
6. **Mapping Configuration** - Validates each mapping item with operators and targets
7. **Execution Configuration** - Validates scheduling configuration

Each step attempts to instantiate the actual service with provided settings, ensuring:
- Required settings are present
- Settings values are valid
- Component dependencies are satisfied
- Type values are registered in the system

## Error Handling

### ValidationResult Structure

```php
$result = $validationService->validateConfiguration($config);

// Check overall validity
$result->isValid(); // bool

// Get errors
$result->getErrors(); // ValidationError[]
$result->hasErrors(); // bool
$result->getErrorMessages(); // string[]

// Get warnings
$result->getWarnings(); // ValidationError[]
$result->hasWarnings(); // bool
$result->getWarningMessages(); // string[]

// Convert to array
$result->toArray(); // ['valid' => bool, 'errors' => [...], 'warnings' => [...]]
```

### ValidationError Structure

```php
$error->getPath(); // "loaderConfig.settings.assetPath"
$error->getMessage(); // "Empty asset path."
$error->toArray(); // ['path' => '...', 'message' => '...']
```

## Examples

### Complete Configuration Example

See the `car-import-clone.yaml` file for a complete, working configuration example.

### Minimal Valid Configuration

```yaml
pimcore_data_hub:
  configurations:
    minimal-import:
      general:
        name: minimal-import
        type: dataImporterDataObject
        active: true
      loaderConfig:
        type: asset
        settings:
          assetPath: /Import/data.json
      interpreterConfig:
        type: json
      resolverConfig:
        elementType: dataObject
        dataObjectClassId: Product
        loadingStrategy:
          type: notLoad
        createLocationStrategy:
          type: staticPath
          settings:
            path: /import/products
        locationUpdateStrategy:
          type: noChange
        publishingStrategy:
          type: noChangeUnpublishNew
      processingConfig:
        executionType: sequential
      mappingConfig:
        - label: name
          dataSourceIndex:
            - name
          dataTarget:
            type: direct
            settings:
              fieldName: name
```

## Best Practices for AI Agents

1. **Start with Schema** - Always query the schema first to understand available options
2. **Validate Incrementally** - Build configuration section by section, validating each
3. **Use Type Information** - The schema provides exact type names and settings requirements
4. **Check Required Fields** - Schema indicates which fields are required vs optional
5. **Handle Extensions** - Custom types will appear in schema alongside built-in types
6. **Provide Context** - Validation errors include paths to help locate issues

## Performance Considerations

- **Validation** - Attempts to instantiate all components, which validates thoroughly but has some overhead
- **Schema Access** - Schema is built once per request, suitable for configuration-time use
- **Caching** - Consider caching schema results for repeated queries in the same process

## Security Notes

- Validation service can be called with `ignorePermissions` flag for system validation
- Schema service is marked as `public: true` to allow access from controllers and commands
- Validation does not execute the import, only checks configuration structure

## Future Enhancements

Potential improvements:

- Configuration builder/wizard using schema information
- Interactive configuration editor with real-time validation
- Configuration templates based on common patterns
- Import/export configuration presets
- Version control integration for configuration changes
