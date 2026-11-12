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

namespace Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Expression language used by the symfony expression operator. Functions can be contributed by
 * services tagged with `pimcore.datahub.data_importer.expression_language_provider`.
 *
 * Two parts of the Symfony expression syntax are rejected, at validation and at evaluation:
 * - the built-in `constant()` function, because it would expose internal PHP constants to import configurations
 * - method calls, because pipeline values can be live elements and the preview, which only requires read
 *   permission on the configuration, must not be able to modify or delete them
 */
class DataImporterExpressionLanguage extends ExpressionLanguage
{
    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        parent::__construct(null, $providers);
    }

    /**
     * @throws SyntaxError when the expression is invalid or calls a method
     */
    public function parse(Expression|string $expression, array $names, int $flags = 0): ParsedExpression
    {
        $parsedExpression = parent::parse($expression, $names, $flags);
        $this->rejectMethodCalls($parsedExpression->getNodes(), (string) $expression);

        return $parsedExpression;
    }

    protected function registerFunctions(): void
    {
        parent::registerFunctions();

        unset($this->functions['constant']);
    }

    private function rejectMethodCalls(Node $node, string $expression): void
    {
        if ($node instanceof GetAttrNode && $node->attributes['type'] === GetAttrNode::METHOD_CALL) {
            throw new SyntaxError('Method calls are not available', 0, $expression);
        }

        foreach ($node->nodes as $childNode) {
            $this->rejectMethodCalls($childNode, $expression);
        }
    }
}
