# Extend via Custom Strategies

The bundle architecture easily allows extension and customization of many
parts of the importing process.

Following strategies and configuration options can be extended all following the same schema:
- Data Sources
- File Formats
- Resolver Loading Strategies
- Element Location Strategies
- Element Publishing Strategies
- Cleanup Strategies
- Transformation Pipeline Operators
- Data Targets


### Extending Schema
Extending one of the listed strategies and configuration options needs following steps. Also, have a look at the current
implementations to see how things are working.

#### 1) PHP Implementation
Implement the required interface (e.g. `DataLoaderInterface` for data sources). Use abstract base classes where available (e.g. `AbstractInterpreter` for file formats).

**Recommended:** Implement `SchemaAwareInterface` for automatic validation and AI agent support:

```php
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class CustomLoader implements DataLoaderInterface, SchemaAwareInterface
{
    public function getSchemaDescription(): string
    {
        return 'Load data from custom source';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        $rootNode = $treeBuilder->getRootNode();
        
        $rootNode
            ->children()
                ->scalarNode('endpoint')->isRequired()->end()
                ->integerNode('timeout')->defaultValue(30)->end()
            ->end();
        
        return $treeBuilder;
    }
}
```

This provides:
- Automatic settings validation
- Schema introspection for tools
- Better error messages

**For Data Targets:** Implement `DataTargetFieldValidatorInterface` to validate field compatibility:

```php
use Pimcore\Bundle\DataImporterBundle\Settings\DataTargetFieldValidatorInterface;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

class CustomDataTarget implements DataTargetInterface, DataTargetFieldValidatorInterface
{
    public function validateTargetField(
        string $transformationResultType,
        string $classId
    ): void {
        // Validate field exists and is compatible with transformation type
        if (!$this->isFieldCompatible($transformationResultType, $classId)) {
            throw new InvalidConfigurationException('Field incompatible');
        }
    }
}
```

**For Operators:** Implement `TransformationTypeAwareInterface` for type validation:

```php
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;

class CustomOperator implements OperatorInterface, TransformationTypeAwareInterface
{
    public function getAcceptedInputTypes(): array
    {
        return ['default', 'numeric'];
    }

    public function getOutputTypes(): array
    {
        return ['numeric'];
    }
}
```

#### 2) Registering implementation as symfony service
The php implementation needs to be registered as symfony service and tagged accordingly.
Tag `name` defines the extension point (e.g. `pimcore.datahub.data_importer.loader` for data sources) and
`type` defines the actual type of the extension. It must be unique and is also the link to values in the configuration file
and the JavaScript implementation.

```yml 
    Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader:
        tags:
            - { name: "pimcore.datahub.data_importer.loader", type: "http" }
```

#### 3) JavaScript Implementation
Create a JavaScript class which meets following requirements:
- Located in a certain namespace depending on the extension point
  (e.g. `pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.*` for data sources).
- Extend the `pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType` class.
- Define a `type` attribute that matches the `type` of the service definition, e.g. `type: 'http'`
- Implement a function `buildSettingsForm` that creates and returns a form with all necessary setting field for the exension.
