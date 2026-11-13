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
use Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\LimitedRangeNode;
use Pimcore\Helper\SymfonyExpression\ReadOnlyValueAdapterInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use stdClass;

class SymfonyExpressionOperatorTest extends Unit
{
    private const SOURCE_VALUE = 'Demo Value';

    protected $tester;

    /**
     * @param ExpressionFunctionProviderInterface[] $providers
     * @param ReadOnlyValueAdapterInterface[] $valueAdapters
     */
    private function createOperator(
        string $expression,
        array $providers = [],
        array $valueAdapters = []
    ): SymfonyExpression {
        $operator = new SymfonyExpression(
            new ApplicationLogger(),
            new DataImporterExpressionLanguage($providers, $valueAdapters)
        );
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

    public function testEnumFunctionIsRejectedAsConfigurationError(): void
    {
        $operator = $this->createOperator("enum('PHP_VERSION')");

        $this->expectException(InvalidConfigurationException::class);
        $operator->evaluateReturnType('string', 1);
    }

    /**
     * @dataProvider provideUnavailableFunctionExpressions
     */
    public function testUnavailableFunctionsAreNotEvaluated(string $expression): void
    {
        $operator = $this->createOperator($expression);

        $this->expectException(InvalidConfigurationException::class);
        $operator->process([]);
    }

    public static function provideUnavailableFunctionExpressions(): array
    {
        return [
            'constant' => ["constant('PHP_VERSION')"],
            'enum' => ["enum('PHP_VERSION')"],
        ];
    }

    /**
     * @dataProvider provideMethodCallExpressions
     */
    public function testMethodsOfPipelineValuesAreNotExecuted(string $expression): void
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
        } catch (InvalidConfigurationException) {
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

    public function testPropertiesOfPipelineValuesAreNotReadable(): void
    {
        $value = new class() {
            public string $name = 'Demo';
        };

        $this->expectException(InvalidConfigurationException::class);
        $this->createOperator('attributes[0].name')->process($value);
    }

    public function testOnlyGettersOfValueAdaptersAreCallable(): void
    {
        $value = new class() {
            public bool $deleted = false;

            public function getName(): string
            {
                return 'Demo';
            }

            public function delete(): void
            {
                $this->deleted = true;
            }
        };
        $valueAdapter = new class($value) implements ReadOnlyValueAdapterInterface {
            public function __construct(private readonly object $supportedValue)
            {
            }

            public function supports(object $value): bool
            {
                return $value === $this->supportedValue;
            }

            public function getGetters(object $value): array
            {
                return ['getname' => static fn (): string => $value->getName()];
            }
        };

        $this->assertSame(
            'Demo',
            $this->createOperator('attributes[0].getName()', [], [$valueAdapter])->process($value)
        );

        try {
            $this->createOperator('attributes[0].delete()', [], [$valueAdapter])->process($value);
            $this->fail('The method call was not rejected.');
        } catch (InvalidConfigurationException) {
            $this->assertFalse($value->deleted);
        }
    }

    public function testPipelineValueIsPassedOnUnchanged(): void
    {
        $value = new stdClass();

        $this->assertSame($value, $this->createOperator('attributes[0]')->process($value));
    }

    /**
     * @dataProvider provideFailingExpressions
     */
    public function testRuntimeErrorIsReportedAsConfigurationError(string $expression, mixed $inputData): void
    {
        $operator = $this->createOperator($expression);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('could not evaluate its expression');
        $operator->process($inputData);
    }

    public static function provideFailingExpressions(): array
    {
        return [
            'division by zero' => ['attributes[0] % attributes[1]', [1, 0]],
            'unsupported operand types' => ['attributes[0] + 1', [['a']]],
            'method call on a scalar' => ['attributes[0].getName()', 'Demo'],
        ];
    }

    public function testRangeWithinLimitIsEvaluated(): void
    {
        $this->assertSame([1, 2, 3], $this->createOperator('1..attributes[0]')->process(3));
        $this->assertSame(
            LimitedRangeNode::MAX_ITEMS,
            $this->createOperator('max(1..attributes[0])')->process(LimitedRangeNode::MAX_ITEMS)
        );
    }

    /**
     * @dataProvider provideOversizedRangeExpressions
     */
    public function testOversizedRangeIsRejected(string $expression, mixed $inputData): void
    {
        $operator = $this->createOperator($expression);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A range can have');
        $operator->process($inputData);
    }

    public static function provideOversizedRangeExpressions(): array
    {
        return [
            'constant bounds' => ['1..999999999', null],
            'bound from the source data' => ['1..attributes[0]', LimitedRangeNode::MAX_ITEMS + 1],
            'descending' => ['attributes[0]..1', LimitedRangeNode::MAX_ITEMS + 1],
            'nested in a function' => ['max(0..attributes[0])', '999999999'],
            'non numeric bound' => ["'a'..999999999", null],
        ];
    }
}
