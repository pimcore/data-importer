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
        $operator = $this->createOperator("attributes[0] == 'Demo Value' ? attributes[1] : null");

        $this->assertSame('second', $operator->process(['Demo Value', 'second']));
        $this->assertNull($operator->process(['other', 'second']));
    }

    public function testEmptyExpressionPassesInputThrough(): void
    {
        $operator = $this->createOperator('   ');

        $this->assertSame(['a', 'b'], $operator->process(['a', 'b']));
        $this->assertSame(TransformationDataTypeService::DEFAULT_TYPE, $operator->evaluateReturnType('string'));
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

    public function testConstantFunctionIsDisabled(): void
    {
        $operator = $this->createOperator("constant('PHP_VERSION')");

        $this->expectException(SyntaxError::class);
        $operator->process([]);
    }
}
