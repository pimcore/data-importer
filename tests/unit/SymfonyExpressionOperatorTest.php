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
use Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\RangeLimitExceededException;
use Pimcore\Helper\SymfonyExpression\ReadOnlyValueAdapterInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use UnexpectedValueException;
use stdClass;

class SymfonyExpressionOperatorTest extends Unit
{
    private const SOURCE_VALUE = 'Demo Value';

    private const DELETE_CALL_EXPRESSION = 'attributes[0].delete()';

    private const OVERSIZED_RANGE_EXPRESSION = '1..999999999';

    private const RANGE_LIMIT_MESSAGE = 'items in total at most';

    private const METHOD_CALL_NOT_REJECTED = 'The method call was not rejected.';

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
     * @dataProvider provideUnavailableFunctionExpressions
     */
    public function testProviderCannotRegisterUnavailableFunctions(string $expression): void
    {
        $provider = new class() implements ExpressionFunctionProviderInterface {
            public function getFunctions(): array
            {
                return [
                    ExpressionFunction::fromPhp('constant'),
                    new ExpressionFunction(
                        'enum',
                        fn ($value) => sprintf('constant(%s)', $value),
                        fn (array $variables, $value): mixed => constant((string) $value)
                    ),
                ];
            }
        };
        $operator = $this->createOperator($expression, [$provider]);

        try {
            $operator->evaluateReturnType('string', 1);
            $this->fail('The function was not rejected when the configuration was validated.');
        } catch (InvalidConfigurationException $exception) {
            $this->assertInstanceOf(SyntaxError::class, $exception->getPrevious());
        }

        $this->expectException(InvalidConfigurationException::class);
        $operator->process([]);
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
            $this->fail(self::METHOD_CALL_NOT_REJECTED);
        } catch (InvalidConfigurationException) {
            $this->assertFalse($element->deleted);
        }
    }

    public static function provideMethodCallExpressions(): array
    {
        return [
            'direct' => [self::DELETE_CALL_EXPRESSION],
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
            $this->createOperator(self::DELETE_CALL_EXPRESSION, [], [$valueAdapter])->process($value);
            $this->fail(self::METHOD_CALL_NOT_REJECTED);
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
            DataImporterExpressionLanguage::MAX_RANGE_ITEMS,
            $this->createOperator('max(1..attributes[0])')->process(DataImporterExpressionLanguage::MAX_RANGE_ITEMS)
        );
    }

    /**
     * @dataProvider provideOversizedRangeExpressions
     */
    public function testOversizedRangeIsRejected(string $expression, mixed $inputData): void
    {
        $operator = $this->createOperator($expression);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(self::RANGE_LIMIT_MESSAGE);
        $operator->process($inputData);
    }

    public static function provideOversizedRangeExpressions(): array
    {
        return [
            'constant bounds' => [self::OVERSIZED_RANGE_EXPRESSION, null],
            'bound from the source data' => ['1..attributes[0]', DataImporterExpressionLanguage::MAX_RANGE_ITEMS + 1],
            'descending' => ['attributes[0]..1', DataImporterExpressionLanguage::MAX_RANGE_ITEMS + 1],
            'nested in a function' => ['max(0..attributes[0])', '999999999'],
            'non numeric bound' => ["'a'..999999999", null],
        ];
    }

    public function testRangeItemsAreLimitedAcrossAllRangesOfAnExpression(): void
    {
        $halfOfLimit = intdiv(DataImporterExpressionLanguage::MAX_RANGE_ITEMS, 2);

        $this->assertCount(
            2,
            $this->createOperator('[1..attributes[0], 1..attributes[0]]')->process($halfOfLimit)
        );

        $operator = $this->createOperator('[1..attributes[0], 1..attributes[0], 1..1]');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(self::RANGE_LIMIT_MESSAGE);
        $operator->process($halfOfLimit);
    }

    public function testManyRangesInOneExpressionAreRejected(): void
    {
        $ranges = array_fill(0, 400, sprintf('1..%d', DataImporterExpressionLanguage::MAX_RANGE_ITEMS));
        $operator = $this->createOperator(sprintf('[%s]', implode(', ', $ranges)));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(self::RANGE_LIMIT_MESSAGE);
        $operator->process(null);
    }

    public function testCharacterRangesCountTowardsTheLimit(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->createOperator("'a'..'c'")->process(null));

        $ranges = array_fill(0, DataImporterExpressionLanguage::MAX_RANGE_ITEMS, "'a'..'b'");
        $operator = $this->createOperator(sprintf('[%s]', implode(', ', $ranges)));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(self::RANGE_LIMIT_MESSAGE);
        $operator->process(null);
    }

    public function testRangeLimitAppliesToEveryEvaluationSeparately(): void
    {
        $operator = $this->createOperator('max(1..attributes[0])');

        $this->assertSame(
            DataImporterExpressionLanguage::MAX_RANGE_ITEMS,
            $operator->process(DataImporterExpressionLanguage::MAX_RANGE_ITEMS)
        );
        $this->assertSame(
            DataImporterExpressionLanguage::MAX_RANGE_ITEMS,
            $operator->process(DataImporterExpressionLanguage::MAX_RANGE_ITEMS)
        );
    }

    public function testRangeLimitErrorIsNotASyntaxError(): void
    {
        try {
            $this->createOperator(self::OVERSIZED_RANGE_EXPRESSION)->process(null);
            $this->fail('The range was not rejected.');
        } catch (InvalidConfigurationException $exception) {
            $this->assertInstanceOf(RangeLimitExceededException::class, $exception->getPrevious());
            $this->assertStringNotContainsString('around position', $exception->getMessage());
        }
    }

    public function testRangeOperatorInsideAStringIsNotARange(): void
    {
        $this->assertSame(
            self::OVERSIZED_RANGE_EXPRESSION,
            $this->createOperator(sprintf("'%s'", self::OVERSIZED_RANGE_EXPRESSION))->process(null)
        );
    }

    public function testErrorOfTheExpressionItselfIsReported(): void
    {
        $value = new class() {
            public bool $deleted = false;

            public function delete(): void
            {
                $this->deleted = true;
            }
        };

        try {
            $this->createOperator(self::DELETE_CALL_EXPRESSION)->process($value);
            $this->fail(self::METHOD_CALL_NOT_REJECTED);
        } catch (InvalidConfigurationException $exception) {
            $this->assertInstanceOf(SyntaxError::class, $exception->getPrevious());
            $this->assertStringContainsString($exception->getPrevious()->getMessage(), $exception->getMessage());
            $this->assertFalse($value->deleted);
        }
    }

    public function testInternalErrorMessageIsNotReported(): void
    {
        $internalMessage = 'SQLSTATE[HY000]: connection to db-internal:3306 failed';
        $provider = new class($internalMessage) implements ExpressionFunctionProviderInterface {
            public function __construct(private readonly string $message)
            {
            }

            public function getFunctions(): array
            {
                return [
                    new ExpressionFunction(
                        'lookup',
                        fn () => '',
                        fn (array $variables): never => throw new UnexpectedValueException($this->message)
                    ),
                ];
            }
        };

        try {
            $this->createOperator('lookup()', [$provider])->process(null);
            $this->fail('The failing function was not reported.');
        } catch (InvalidConfigurationException $exception) {
            $this->assertStringNotContainsString($internalMessage, $exception->getMessage());
            $this->assertStringContainsString('could not evaluate its expression', $exception->getMessage());
            $this->assertSame($internalMessage, $exception->getPrevious()?->getMessage());
        }
    }
}
