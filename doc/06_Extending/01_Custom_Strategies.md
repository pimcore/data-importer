---
title: Custom Strategies
description: Add your own data source, file format, operator, data target or resolver strategy.
---

# Custom Strategies

Most parts of the import process are pluggable. Each one follows the same pattern: implement a PHP interface, register the
implementation as a tagged Symfony service, and add a matching settings form to Pimcore Studio.

## Extension Points

| Extension point | Service tag | Interface | Selectable in Studio |
|---|---|---|---|
| Data source | `pimcore.datahub.data_importer.loader` | `DataSource\Loader\DataLoaderInterface` | Yes |
| File format | `pimcore.datahub.data_importer.interpreter` | `DataSource\Interpreter\InterpreterInterface` | Yes |
| Transformation operator | `pimcore.datahub.data_importer.operator` | `Mapping\Operator\OperatorInterface` | Yes |
| Data target | `pimcore.datahub.data_importer.data_target` | `Mapping\DataTarget\DataTargetInterface` | Yes |
| Element loading strategy | `pimcore.datahub.data_importer.resolver.load` | `Resolver\Load\LoadStrategyInterface` | Yes |
| Element location strategy | `pimcore.datahub.data_importer.resolver.location` | `Resolver\Location\LocationStrategyInterface` | Yes |
| Element publishing strategy | `pimcore.datahub.data_importer.resolver.publish` | `Resolver\Publish\PublishStrategyInterface` | Yes |
| Element factory | `pimcore.datahub.data_importer.resolver.factory` | `Resolver\Factory\FactoryInterface` | No |
| Cleanup strategy | `pimcore.datahub.data_importer.cleanup` | `Cleanup\CleanupStrategyInterface` | No |

All interfaces live under the `Pimcore\Bundle\DataImporterBundle` namespace.

The last two rows are backend-only. The **Cleanup Strategy** select in the processing settings offers the shipped
`delete` and `unpublish` options from a hard-coded list, and the element factory is not exposed in the configuration
panel at all. A custom implementation of either is loaded correctly at import time, but there is no Studio registry to
register it into, so it cannot be picked in the panel. Reaching it requires writing the `type` into the stored
configuration through another route, or replacing the corresponding Studio component in your own plugin.

## 1. Implement the PHP Class

Implement the interface of the extension point. Several extension points ship an abstract base class that already covers
the repetitive parts, for example `AbstractInterpreter` for file formats and `AbstractOperator` for operators. Extending
an existing implementation works too: the `Many-to-Many Relation` data target extends `Direct`, and the SQL file format
extends the JSON one.

Study the shipped implementations before writing your own. They are the reference for the contract each interface
expects.

## 2. Declare a Settings Schema

Three optional interfaces describe an implementation's settings to the rest of the bundle. They are not required to make
a strategy work, but without them its settings are neither validated nor visible to the
[configuration validation](./03_Configuration_Validation.md) or to the MCP tools.

`SchemaAwareInterface` describes the `settings` block of an implementation as a Symfony config tree. The bundle uses it
to validate a stored configuration and to tell a caller which options exist:

```php
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class CustomLoader implements DataLoaderInterface, SchemaAwareInterface
{
    public function getSchemaDescription(): string
    {
        return 'Load data from a custom source';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('endpoint')->info('URL to read from')->isRequired()->end()
                ->integerNode('timeout')->info('Request timeout in seconds')->defaultValue(30)->end()
            ->end();

        return $treeBuilder;
    }
}
```

Write an `info()` on every node. It is the only description a caller gets, and the MCP tools surface it verbatim.

`TransformationTypeAwareInterface` declares which transformation data types an operator accepts and produces, so that a
mapping can be checked before it runs:

```php
public function getAcceptedInputTypes(): array
{
    return [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC];
}
```

`DataTargetFieldValidatorInterface` lets a data target reject a transformation result that its target field cannot
store, and throw `InvalidConfigurationException` with a message naming the field.

## 3. Register the Service

Register the class as a Symfony service and tag it. The tag `name` selects the extension point. The tag `type` is the
identifier of your implementation: it has to be unique, it is stored in the saved import configuration, and it links the
PHP class to its Studio settings form.

```yml
services:
    Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader:
        tags:
            - { name: "pimcore.datahub.data_importer.loader", type: "http" }
```

At this point the import runs with your implementation, but nobody can select it in Pimcore Studio yet.

## 4. Add the Studio Settings Form

This step applies to the extension points marked selectable above. The configuration panel is a Pimcore Studio plugin.
Those extension points have a registry in the Studio dependency injection container, and each selectable option is a
*dynamic type* registered into it.

Create a dynamic type that extends the abstract class of the extension point. Its `id` has to match the `type` of the
service tag, `label` is a translation key, and `renderSettings()` returns the settings form:

```tsx
import React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { DynamicTypeLoaderAbstract } from '.../dynamic-type-loader-abstract'
import { AssetLoaderSettings } from './asset-loader-settings'

@injectable()
export class DynamicTypeLoaderAsset extends DynamicTypeLoaderAbstract {
  readonly id = 'asset'
  readonly label = 'data-importer.loader.asset'

  renderSettings (_configName: string): React.JSX.Element | null {
    return <AssetLoaderSettings />
  }
}
```

Bind the type in your plugin and register it with the matching registry. The registries are resolved from the container by
these service identifiers:

| Extension point | Registry service identifier |
|---|---|
| Data source | `DataImporter/DynamicTypes/Loader/Registry` |
| File format | `DataImporter/DynamicTypes/Interpreter/Registry` |
| Transformation operator | `DataImporter/DynamicTypes/Transformer/Registry` |
| Data target | `DataImporter/DynamicTypes/DataTarget/Registry` |
| Resolver strategies | `DataImporter/DynamicTypes/Resolver/Registry` |

```ts
const loaderRegistry = container.get<DynamicTypeLoaderRegistry>('DataImporter/DynamicTypes/Loader/Registry')
loaderRegistry.registerDynamicType(container.get('DataImporter/DynamicTypes/Loader/Asset'))
```

The order of registration determines the order of the options in the UI.

For the plugin scaffolding itself, see the
[Studio UI Bundle extension documentation](https://github.com/pimcore/studio-ui-bundle/blob/2026.x/doc/04_Extending/README.md).

:::note

An operator that needs no settings has no form to render. The bundle registers those through a shared helper rather than
a dedicated class per operator.

:::
