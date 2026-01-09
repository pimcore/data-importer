# Making Services Schema-Aware for AI Agents

This document explains how to make your custom Data Importer services provide schema information for AI agents and automated configuration tools.

## Overview

The Data Importer uses the `SchemaAwareInterface` to allow services to provide their own metadata. This enables:
- **Extensibility** - Custom services automatically appear in schema output
- **Documentation** - Services document themselves
- **AI Support** - Automated tools can discover and use custom services
- **No Central Registry** - No need to modify ConfigurationSchemaService

## The SchemaAwareInterface

Located at `src/Settings/SchemaAwareInterface.php`, this interface has two methods:

```php
interface SchemaAwareInterface
{
    /**
     * Get human-readable description of what this service does
     */
    public function getSchemaDescription(): string;

    /**
     * Get schema for settings this service accepts
     * Returns array with structure:
     * [
     *     'settingName' => [
     *         'type' => 'string|integer|boolean|array|object',
     *         'description' => 'What this setting does',
     *         'required' => true|false,
     *         'default' => 'default value' (optional),
     *         'enum' => ['allowed', 'values'] (optional),
     *     ]
     * ]
     */
    public function getSettingsSchema(): array;
}
```

## Implementing SchemaAwareInterface

### Example 1: Data Loader

```php
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;

class MyCustomLoader implements DataLoaderInterface, SchemaAwareInterface
{
    protected string $apiEndpoint;
    protected ?string $apiKey;
    protected int $timeout;

    public function setSettings(array $settings): void
    {
        if (empty($settings['apiEndpoint'])) {
            throw new InvalidConfigurationException('API endpoint is required');
        }
        
        $this->apiEndpoint = $settings['apiEndpoint'];
        $this->apiKey = $settings['apiKey'] ?? null;
        $this->timeout = $settings['timeout'] ?? 30;
    }

    public function getSchemaDescription(): string
    {
        return 'Load data from custom REST API endpoint';
    }

    public function getSettingsSchema(): array
    {
        return [
            'apiEndpoint' => [
                'type' => 'string',
                'description' => 'Full URL of the API endpoint to load data from',
                'required' => true,
            ],
            'apiKey' => [
                'type' => 'string',
                'description' => 'Optional API key for authentication',
                'required' => false,
            ],
            'timeout' => [
                'type' => 'integer',
                'description' => 'Request timeout in seconds',
                'required' => false,
                'default' => 30,
            ],
        ];
    }

    // ... implement other DataLoaderInterface methods
}
```

### Example 2: Location Strategy

```php
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;

class DynamicPathStrategy implements LocationStrategyInterface, SchemaAwareInterface
{
    protected string $pathTemplate;
    protected array $pathVariables;

    public function setSettings(array $settings): void
    {
        $this->pathTemplate = $settings['pathTemplate'] ?? '/import/{year}/{month}';
        $this->pathVariables = $settings['pathVariables'] ?? ['year', 'month'];
    }

    public function getSchemaDescription(): string
    {
        return 'Create dynamic paths based on data fields and date patterns';
    }

    public function getSettingsSchema(): array
    {
        return [
            'pathTemplate' => [
                'type' => 'string',
                'description' => 'Path template with placeholders like /import/{year}/{category}',
                'required' => true,
                'default' => '/import/{year}/{month}',
            ],
            'pathVariables' => [
                'type' => 'array',
                'description' => 'Array of variable names to substitute in the template',
                'required' => false,
                'default' => ['year', 'month'],
            ],
        ];
    }

    // ... implement other LocationStrategyInterface methods
}
```

### Example 3: Transformation Operator

```php
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;

class EncryptOperator implements OperatorInterface, SchemaAwareInterface
{
    protected string $algorithm;
    protected string $key;

    public function setSettings(array $settings): void
    {
        $this->algorithm = $settings['algorithm'] ?? 'aes-256-gcm';
        $this->key = $settings['key'];
    }

    public function getSchemaDescription(): string
    {
        return 'Encrypt field values using specified algorithm';
    }

    public function getSettingsSchema(): array
    {
        return [
            'algorithm' => [
                'type' => 'string',
                'description' => 'Encryption algorithm to use',
                'required' => false,
                'default' => 'aes-256-gcm',
                'enum' => ['aes-256-gcm', 'aes-256-cbc', 'aes-128-gcm'],
            ],
            'key' => [
                'type' => 'string',
                'description' => 'Encryption key (must be kept secret)',
                'required' => true,
            ],
        ];
    }

    // ... implement other OperatorInterface methods
}
```

## Service Registration

Register your service with appropriate tags:

```yaml
# config/services.yaml
services:
    App\DataImporter\Loader\MyCustomLoader:
        tags:
            - { name: "pimcore.datahub.data_importer.loader", type: "customApi" }

    App\DataImporter\Location\DynamicPathStrategy:
        tags:
            - { name: "pimcore.datahub.data_importer.resolver.location", type: "dynamicPath" }

    App\DataImporter\Operator\EncryptOperator:
        tags:
            - { name: "pimcore.datahub.data_importer.operator", type: "encrypt" }
```

## Schema Output

Once registered and implementing SchemaAwareInterface, your service automatically appears in schema queries:

### CLI Query
```bash
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig
```

### Output
```
loaderConfig
============

Available Types:
----------------

  customApi
    Load data from custom REST API endpoint
    Settings:
      * apiEndpoint (string)
        Full URL of the API endpoint to load data from
        apiKey (string)
        Optional API key for authentication
        timeout (integer)
        Request timeout in seconds
```

### JSON Output for AI Agents
```bash
bin/console datahub:data-importer:validate-config --schema --json
```

```json
{
  "loaderConfig": {
    "availableTypes": {
      "customApi": {
        "type": "customApi",
        "class": "App\\DataImporter\\Loader\\MyCustomLoader",
        "description": "Load data from custom REST API endpoint",
        "settings": {
          "apiEndpoint": {
            "type": "string",
            "description": "Full URL of the API endpoint to load data from",
            "required": true
          },
          "apiKey": {
            "type": "string",
            "description": "Optional API key for authentication",
            "required": false
          },
          "timeout": {
            "type": "integer",
            "description": "Request timeout in seconds",
            "required": false,
            "default": 30
          }
        }
      }
    }
  }
}
```

## Applicable Service Types

You can implement SchemaAwareInterface on any of these service types:

| Service Type | Tag | Interface |
|-------------|-----|-----------|
| Data Loaders | `pimcore.datahub.data_importer.loader` | `DataLoaderInterface` |
| Interpreters | `pimcore.datahub.data_importer.interpreter` | `InterpreterInterface` |
| Load Strategies | `pimcore.datahub.data_importer.resolver.load` | `LoadStrategyInterface` |
| Location Strategies | `pimcore.datahub.data_importer.resolver.location` | `LocationStrategyInterface` |
| Publishing Strategies | `pimcore.datahub.data_importer.resolver.publish` | `PublishStrategyInterface` |
| Operators | `pimcore.datahub.data_importer.operator` | `OperatorInterface` |
| Data Targets | `pimcore.datahub.data_importer.data_target` | `DataTargetInterface` |
| Cleanup Strategies | `pimcore.datahub.data_importer.cleanup` | `CleanupStrategyInterface` |

## Best Practices

### 1. Clear Descriptions
Write descriptions that explain **what** the service does and **when** to use it:

```php
// ❌ Too vague
public function getSchemaDescription(): string
{
    return 'Loads data';
}

// ✅ Clear and specific
public function getSchemaDescription(): string
{
    return 'Load data from SFTP server with automatic retry and connection pooling';
}
```

### 2. Complete Settings Documentation
Document all settings including optional ones:

```php
public function getSettingsSchema(): array
{
    return [
        'requiredField' => [
            'type' => 'string',
            'description' => 'Clear description of what this field does',
            'required' => true,
        ],
        'optionalField' => [
            'type' => 'integer',
            'description' => 'What happens if this is not set',
            'required' => false,
            'default' => 100,
        ],
    ];
}
```

### 3. Use Enums for Limited Options
Specify allowed values when applicable:

```php
'format' => [
    'type' => 'string',
    'description' => 'Output format for the data',
    'required' => false,
    'enum' => ['json', 'xml', 'csv'],
    'default' => 'json',
],
```

### 4. Provide Examples in Descriptions
Help users understand complex settings:

```php
'pathTemplate' => [
    'type' => 'string',
    'description' => 'Template with placeholders. Example: /import/{year}/{category}',
    'required' => true,
],
```

## Fallback Behavior

If a service does NOT implement SchemaAwareInterface:
- The ConfigurationSchemaService will show a basic description derived from the class name
- Settings will be empty: `"settings": []`
- Validation still works, but documentation is limited

This ensures backward compatibility with existing services while encouraging new services to provide proper schema information.

## Benefits

### For Developers
- Self-documenting services
- No need to update central schema service
- Easier maintenance

### For AI Agents
- Complete service discovery
- Automatic schema updates
- Rich metadata for code generation

### For Users
- Better documentation
- Clear settings requirements
- Consistent configuration experience

## Migration Guide

To migrate existing services:

1. Add `SchemaAwareInterface` to the class implements list
2. Add the two required methods
3. Move hardcoded descriptions from ConfigurationSchemaService to the service itself
4. Test with: `bin/console datahub:data-importer:validate-config --schema-section=yourSection`

Example migration:
```php
// Before
class MyLoader implements DataLoaderInterface
{
    // ... existing code
}

// After
class MyLoader implements DataLoaderInterface, SchemaAwareInterface
{
    // ... existing code
    
    public function getSchemaDescription(): string
    {
        return 'Your service description';
    }

    public function getSettingsSchema(): array
    {
        return [/* your settings */];
    }
}
```
