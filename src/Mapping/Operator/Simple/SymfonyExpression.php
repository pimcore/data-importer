<?php

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

    public function process(mixed $inputData, bool $dryRun = false): mixed
    {
        if ($this->expression === '') {
            return $inputData;
        }

        return $this->expressionLanguage->evaluate($this->expression, [self::VARIABLE_NAME => $inputData]);
    }

    /**
     * @throws InvalidConfigurationException when the configured expression cannot be parsed
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($this->expression !== '') {
            try {
                $this->expressionLanguage->lint($this->expression, [self::VARIABLE_NAME]);
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
        }

        return TransformationDataTypeService::DEFAULT_TYPE;
    }
}
