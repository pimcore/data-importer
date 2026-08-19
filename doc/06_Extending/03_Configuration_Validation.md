# Configuration Validation

Validate Data Importer configurations programmatically to catch errors before runtime.

## Command Line Usage

```bash
# Validate a configuration
bin/console datahub:data-importer:validate-config my-import-config

# Show configuration schema
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig

# Output as JSON
bin/console datahub:data-importer:validate-config my-import-config --json
```

## Programmatic Validation

```php
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;

$validationService = \Pimcore::getContainer()
    ->get(ConfigurationValidationService::class);

$result = $validationService->validateConfiguration($configuration);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        echo "{$error->getPath()}: {$error->getMessage()}\n";
    }
}
```

The validation service checks:
- Required fields and structure
- Type values against registered services
- Settings compatibility
- Component instantiation

## Schema Introspection

Query available configuration options and their requirements:

```php
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;

$schemaService = \Pimcore::getContainer()->get(ConfigurationSchemaService::class);

// Get available loaders, interpreters, operators, etc.
$loaderSchema = $schemaService->getLoaderConfigSchema();
$availableTypes = array_keys($loaderSchema['availableTypes']);
```

Schema includes available types, required fields, constraints, and descriptions for each configuration section.

## Validation Interfaces for Custom Components

Implement these interfaces to enable validation and AI agent support:

- **`SchemaAwareInterface`** - For loaders, interpreters, resolvers, and operators. See [Custom Strategies](01_Custom_Strategies.md).
- **`DataTargetFieldValidatorInterface`** - For data targets to validate field-level settings and provide field-specific schemas.
- **`TransformationTypeAwareInterface`** - For operators to declare accepted input types and output types for transformation data type validation.
