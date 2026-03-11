# Adding Expression Function Providers

The **Symfony Expression** operator in the data importer transformation pipeline
supports custom functions via the Symfony Expression Language provider mechanism.
This allows you to register reusable functions (e.g. `concat`, `replace`, `contains`)
that can be called inside any expression configured in the operator.

## How it works

The operator evaluates expressions using `Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\DataImporterExpressionLanguage`,
which is a thin wrapper around Symfony's `ExpressionLanguage`. On boot, the container
collects all services tagged with `pimcore.datahub.data_importer.expression_language_provider`
and registers their functions automatically.

## Creating a provider

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
                'myFunction',
                // Compiler (for compiled expressions — can throw if not needed)
                function ($arg) {
                    return sprintf('strtolower(%s)', $arg);
                },
                // Evaluator (used at runtime)
                function (array $variables, $value): string {
                    return strtolower((string) $value);
                }
            ),
        ];
    }
}
```

Each `ExpressionFunction` takes three arguments:
- **name** — the function name as used in expressions
- **compiler** — a callable that returns a PHP expression string (used when expressions are compiled to PHP)
- **evaluator** — a callable `(array $variables, ...$args)` that performs the actual work at runtime

## Registering the provider

Tag the service in `config/services.yaml`:

```yaml
App\ExpressionLanguage\MyFunctionsProvider:
    tags:
        - { name: 'pimcore.datahub.data_importer.expression_language_provider' }
```

> **Note:** If you also want the same functions available in **Calculated Value** fields,
> add the `pimcore.calculated_value.expression_language_provider` tag as well.

## Using functions in an expression

Once registered, the function name is available directly in any Symfony Expression
operator. Input column values are exposed as `attributes[0]`, `attributes[1]`, etc.

```
myFunction(attributes[0])
```
