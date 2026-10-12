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

## 2. Register the Service

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

## 3. Add the Studio Settings Form

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

The abstract classes are not published as a package yet (see
[pimcore/data-importer#628](https://github.com/pimcore/data-importer/issues/628)). Until then, extend
`DynamicTypeAbstract` from `@pimcore/studio-ui-bundle/modules/element` and declare the same properties as the
abstract class of the extension point. The registry only relies on those properties.

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
To replace a shipped type with your own, call `overrideDynamicType()` instead of `registerDynamicType()`.

### Resolver Strategies

All resolver strategies share one registry. A resolver type differs from the other extension points in three
properties:

- `group` selects the panel: `loading`, `createLocation`, `updateLocation` or `publishing`.
- `type` has to match the `type` of the service tag. It is written into the configuration.
- `id` is `<group>.<type>`, so the same `type` can be registered in more than one group.

`renderSettings()` receives the column headers, languages and classes of the current configuration. Bind the settings
form to the `settings` of the configuration key of the group:

| Group | Configuration key |
|---|---|
| `loading` | `resolverConfig.loadingStrategy` |
| `createLocation` | `resolverConfig.createLocationStrategy` |
| `updateLocation` | `resolverConfig.locationUpdateStrategy` |
| `publishing` | `resolverConfig.publishingStrategy` |

```tsx
import React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { Form, Input } from '@pimcore/studio-ui-bundle/components'
import { DynamicTypeAbstract } from '@pimcore/studio-ui-bundle/modules/element'

interface ResolverRenderProps {
  columnHeaderOptions: Array<{ value: string, label: string }>
  languageOptions?: Array<{ value: string, label: string }>
  classOptions?: Array<{ value: string, label: string }>
  isLoadingClasses?: boolean
}

@injectable()
export class DynamicTypeResolverMyStrategyCreation extends DynamicTypeAbstract {
  readonly id = 'createLocation.myStrategy'
  readonly type = 'myStrategy'
  readonly label = 'app.resolver.location-strategy.myStrategy'
  readonly group = 'createLocation'

  renderSettings (props: ResolverRenderProps): React.JSX.Element | null {
    return (
      <Form.Item name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'rootPath'] }>
        <Input />
      </Form.Item>
    )
  }
}
```

A location strategy that should also be offered for existing elements needs a second type with
`group = 'updateLocation'`, `id = 'updateLocation.myStrategy'` and a form bound to
`resolverConfig.locationUpdateStrategy.settings`.

For the plugin scaffolding itself, see the
[Studio UI Bundle extension documentation](https://github.com/pimcore/studio-ui-bundle/blob/2026.x/doc/04_Extending/README.md).

:::note

An operator that needs no settings has no form to render. The bundle registers those through a shared helper rather than
a dedicated class per operator.

:::
