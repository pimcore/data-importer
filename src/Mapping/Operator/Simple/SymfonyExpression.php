<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\DataImporterExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

/**
 * Evaluates a Symfony Expression Language expression against the source values of the mapping,
 * which are exposed as `attributes[0]`, `attributes[1]`, ... in the order of the selected source columns.
 *
 * @internal
 */
final class SymfonyExpression extends AbstractOperator
{
    private const VARIABLE_NAME = 'attributes';

    private string $expression = '';

    public function __construct(
        ApplicationLogger $applicationLogger,
        private readonly DataImporterExpressionLanguage $expressionLanguage,
    ) {
        parent::__construct($applicationLogger);
    }

    public function setSettings(array $settings): void
    {
        $this->expression = trim((string) ($settings['expression'] ?? ''));
    }

    /**
     * @throws InvalidConfigurationException when the expression fails for the given input
     */
    public function process(mixed $inputData, bool $dryRun = false): mixed
    {
        if ($this->expression === '') {
            return $inputData;
        }

        // A mapping with a single source column hands over the bare value instead of a list.
        $attributes = is_array($inputData) ? $inputData : [$inputData];

        try {
            return $this->expressionLanguage->evaluate($this->expression, [self::VARIABLE_NAME => $attributes]);
        } catch (Throwable $exception) {
            throw new InvalidConfigurationException(
                sprintf('Symfony expression operator could not evaluate its expression: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * @throws InvalidConfigurationException when the configured expression is not valid
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($this->expression === '') {
            return $inputType;
        }

        try {
            $this->expressionLanguage->parse($this->expression, [self::VARIABLE_NAME]);
        } catch (SyntaxError $exception) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Invalid expression for symfony expression operator at transformation position %s: %s',
                    $index,
                    $exception->getMessage()
                ),
                0,
                $exception
            );
        }

        return TransformationDataTypeService::DEFAULT_TYPE;
    }
}
