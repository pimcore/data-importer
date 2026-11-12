<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\SymfonyExpression;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\DataImporterExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class SymfonyExpressionOperatorTest extends Unit
{
    private const SOURCE_VALUE = 'Demo Value';

    protected $tester;

    /**
     * @param ExpressionFunctionProviderInterface[] $providers
     */
    private function createOperator(string $expression, array $providers = []): SymfonyExpression
    {
        $operator = new SymfonyExpression(new ApplicationLogger(), new DataImporterExpressionLanguage($providers));
        $operator->setSettings(['expression' => $expression]);

        return $operator;
    }

    public function testEvaluatesExpressionAgainstSourceAttributes(): void
    {
        $operator = $this->createOperator(
            sprintf("attributes[0] == '%s' ? attributes[1] : null", self::SOURCE_VALUE)
        );

        $this->assertSame('second', $operator->process([self::SOURCE_VALUE, 'second']));
        $this->assertNull($operator->process(['other', 'second']));
    }

    public function testEmptyExpressionPassesInputThrough(): void
    {
        $operator = $this->createOperator('   ');

        $this->assertSame(['a', 'b'], $operator->process(['a', 'b']));
        $this->assertSame('scalar', $operator->process('scalar'));
        $this->assertSame(
            TransformationDataTypeService::DEFAULT_ARRAY,
            $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY)
        );
        $this->assertSame(
            TransformationDataTypeService::DATA_OBJECT,
            $operator->evaluateReturnType(TransformationDataTypeService::DATA_OBJECT)
        );
    }

    public function testExpressionResultIsReportedAsDefaultType(): void
    {
        $operator = $this->createOperator('attributes[0]');

        $this->assertSame(
            TransformationDataTypeService::DEFAULT_TYPE,
            $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY)
        );
    }

    public function testSingleSourceValueIsAvailableAsFirstAttribute(): void
    {
        $operator = $this->createOperator("attributes[0] ?? 'fallback'");

        $this->assertSame(self::SOURCE_VALUE, $operator->process(self::SOURCE_VALUE));
        $this->assertSame(42, $operator->process(42));
        $this->assertSame('fallback', $operator->process(null));
    }

    public function testFunctionsFromTaggedProvidersAreAvailable(): void
    {
        $provider = new class() implements ExpressionFunctionProviderInterface {
            public function getFunctions(): array
            {
                return [
                    new ExpressionFunction(
                        'lower',
                        fn ($value) => sprintf('strtolower(%s)', $value),
                        fn (array $variables, $value): string => strtolower((string) $value)
                    ),
                ];
            }
        };

        $operator = $this->createOperator('lower(attributes[0])', [$provider]);

        $this->assertSame('demo', $operator->process(['DEMO']));
    }

    public function testInvalidExpressionIsRejectedAsConfigurationError(): void
    {
        $operator = $this->createOperator('attributes[0] ==');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('transformation position 3');
        $operator->evaluateReturnType('string', 3);
    }

    public function testUnknownVariablesAreRejectedAsConfigurationError(): void
    {
        $operator = $this->createOperator('record[0]');

        $this->expectException(InvalidConfigurationException::class);
        $operator->evaluateReturnType('string', 1);
    }

    public function testConstantFunctionIsRejectedAsConfigurationError(): void
    {
        $operator = $this->createOperator("constant('PHP_VERSION')");

        $this->expectException(InvalidConfigurationException::class);
        $operator->evaluateReturnType('string', 1);
    }

    public function testMethodCallIsRejectedAsConfigurationError(): void
    {
        $operator = $this->createOperator('attributes[0].delete()');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Method calls are not available');
        $operator->evaluateReturnType('string', 1);
    }

    /**
     * @dataProvider provideMethodCallExpressions
     */
    public function testMethodCallIsNotExecuted(string $expression): void
    {
        $element = new class() {
            public bool $deleted = false;

            public function delete(): void
            {
                $this->deleted = true;
            }
        };
        $operator = $this->createOperator($expression);

        try {
            $operator->process($element, true);
            $this->fail('The method call was not rejected.');
        } catch (SyntaxError) {
            $this->assertFalse($element->deleted);
        }
    }

    public static function provideMethodCallExpressions(): array
    {
        return [
            'direct' => ['attributes[0].delete()'],
            'null safe' => ['attributes[0]?.delete()'],
            'nested in a condition' => ["attributes[0] ? [attributes[0].delete()] : 'none'"],
            'as function argument' => ['max(1, attributes[0].delete())'],
        ];
    }

    public function testPublicPropertiesStayReadable(): void
    {
        $value = new class() {
            public string $name = 'Demo';
        };

        $this->assertSame('Demo', $this->createOperator('attributes[0].name')->process($value));
    }

    public function testConstantFunctionIsDisabled(): void
    {
        $operator = $this->createOperator("constant('PHP_VERSION')");

        $this->expectException(SyntaxError::class);
        $operator->process([]);
    }
}
