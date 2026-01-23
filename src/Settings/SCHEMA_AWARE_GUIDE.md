# Making Services Schema-Aware for AI Agents

Make custom Data Importer services self-documenting by implementing `SchemaAwareInterface`.

## The Interface

```php
interface SchemaAwareInterface
{
    public function getSchemaDescription(): string;
    public function getConfigTreeBuilder(): ?TreeBuilder;
}
```

## Implementation Example

```php
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class StaticPathStrategy implements LocationStrategyInterface, SchemaAwareInterface
{
    private string $path;

    public function setSettings(array $settings): void
    {
        if (empty($settings['path'])) {
            throw new InvalidConfigurationException('Empty path.');
        }
        $this->path = $settings['path'];
    }

    public function getSchemaDescription(): string
    {
        return 'Use a static path for all elements';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('path')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Static path where elements will be created (e.g., /import/products)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
```

## TreeBuilder Quick Reference

```php
$rootNode
    ->children()
        // Required string
        ->scalarNode('fieldName')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Description')
        ->end()
        
        // Optional with default
        ->scalarNode('optional')
            ->defaultValue('default')
            ->info('Description')
        ->end()
        
        // Integer with constraints
        ->integerNode('count')
            ->min(1)
            ->max(100)
            ->defaultValue(10)
        ->end()
        
        // Boolean
        ->booleanNode('enabled')
            ->defaultTrue()
        ->end()
        
        // Enum
        ->enumNode('type')
            ->values(['option1', 'option2', 'option3'])
            ->defaultValue('option1')
        ->end()
        
        // Array
        ->arrayNode('items')
            ->scalarPrototype()->end()
        ->end()
    ->end();
```

## Service Registration

```yaml
# config/services.yaml
services:
    App\DataImporter\Loader\ApiLoader:
        tags:
            - { name: pimcore.datahub.data_importer.loader, type: customApi }
```

### Available Service Tags

| Service Type | Tag |
|-------------|-----|
| Data Loaders | `pimcore.datahub.data_importer.loader` |
| Interpreters | `pimcore.datahub.data_importer.interpreter` |
| Load Strategies | `pimcore.datahub.data_importer.resolver.load` |
| Location Strategies | `pimcore.datahub.data_importer.resolver.location` |
| Publishing Strategies | `pimcore.datahub.data_importer.resolver.publish` |
| Operators | `pimcore.datahub.data_importer.operator` |
| Data Targets | `pimcore.datahub.data_importer.data_target` |
| Cleanup Strategies | `pimcore.datahub.data_importer.cleanup` |

## Viewing Schema Output

```bash
# Show schema for specific section
bin/console datahub:data-importer:validate-config --schema-section=loaderConfig

# JSON output for AI agents
bin/console datahub:data-importer:validate-config --schema --json
```

## Best Practices

**Write specific descriptions:**
```php
// ❌ Vague
return 'Loads data';

// ✅ Specific  
return 'Load data from SFTP server with automatic retry and connection pooling';
```

**Use TreeBuilder constraints for validation:**
- `isRequired()` / `cannotBeEmpty()` for required fields
- `min()` / `max()` for numeric ranges
- `enumNode()` for limited options
- `defaultValue()` for sensible defaults
- `info()` for helpful descriptions

**Return null for services without settings:**
```php
public function getConfigTreeBuilder(): ?TreeBuilder
{
    return null; // No settings required
}
```

## Benefits

- **Self-documenting** - Services describe themselves
- **AI-friendly** - Automatic schema discovery for agents
- **No central registry** - No need to modify ConfigurationSchemaService
- **Backward compatible** - Services without interface still work
