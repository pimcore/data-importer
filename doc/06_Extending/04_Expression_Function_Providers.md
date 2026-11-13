---
title: Expression Function Providers
description: Add custom functions to the Symfony Expression operator.
---

# Expression Function Providers

The **Symfony Expression** operator of the
[transformation pipeline](../03_Configuration/05_Mapping_Configuration/01_Transformation_Pipeline.md) evaluates its
expression with `DataImporterExpressionLanguage` from the namespace
`Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage`, which builds on the
`Pimcore\Helper\SymfonyExpression\ReadOnlyExpressionLanguage` of the Pimcore core. Every service tagged with
`pimcore.datahub.data_importer.expression_language_provider` contributes its functions to that expression language, so
reusable functions (for example `concat`, `replace` or `contains`) become available in every expression configured in
the operator.

Expressions only get read-only stand-ins of the pipeline values, so they can call getters on elements and their field
values but nothing else. Functions are application code: they receive the original values, and elements they return
are made read-only again before the expression sees them. To let expressions call getters on objects of your own,
implement `Pimcore\Helper\SymfonyExpression\ReadOnlyValueAdapterInterface`.

The built-in `constant()` and `enum()` functions are not available, also not when a provider registers a function with
one of these names.

## Creating a Provider

Implement `Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface`:

```php
<?php

declare(strict_types=1);

namespace App\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class MyFunctionsProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'lower',
                // compiler: returns PHP code, required by Symfony but not used by the operator
                fn ($value) => sprintf('strtolower(%s)', $value),
                // evaluator: used at runtime
                fn (array $variables, $value): string => strtolower((string) $value)
            ),
        ];
    }
}
```

Each `ExpressionFunction` takes three arguments:

- **name**: the function name as used in expressions
- **compiler**: a callable that returns a PHP expression string. Symfony requires it, but the operator never compiles
  expressions.
- **evaluator**: a callable `(array $variables, ...$args)` that performs the actual work at runtime

## Registering the Provider

Tag the service in `config/services.yaml`:

```yaml
App\ExpressionLanguage\MyFunctionsProvider:
    tags:
        - { name: 'pimcore.datahub.data_importer.expression_language_provider' }
```

## Using the Function

Once registered, the function is available in any Symfony Expression operator. The source values are exposed as
`attributes[0]`, `attributes[1]`, … in the order of the selected source columns:

```
lower(attributes[0])
```
