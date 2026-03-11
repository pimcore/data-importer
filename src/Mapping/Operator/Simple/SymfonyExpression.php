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

use Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage\DataImporterExpressionLanguage;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Symfony\Contracts\Service\Attribute\Required;

class SymfonyExpression extends AbstractOperator
{
    protected string $expression = '';

    private DataImporterExpressionLanguage $expressionLanguage;

    #[Required]
    public function setExpressionLanguage(DataImporterExpressionLanguage $expressionLanguage): void
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function setSettings(array $settings): void
    {
        $this->expression = trim($settings['expression'] ?? '');
    }

    public function process($inputData, bool $dryRun = false): mixed
    {
        if (empty($this->expression)) {
            return $inputData;
        }

        return $this->expressionLanguage->evaluate($this->expression, ['attributes' => $inputData]);
    }

    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        return TransformationDataTypeService::DEFAULT_TYPE;
    }
}
