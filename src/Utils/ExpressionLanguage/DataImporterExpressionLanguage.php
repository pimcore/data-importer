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

namespace Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage;

use LogicException;
use Pimcore\Helper\SymfonyExpression\ReadOnlyExpressionLanguage;
use Pimcore\Helper\SymfonyExpression\ReadOnlyValueAdapterInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\Token;

/**
 * Expression language used by the symfony expression operator. Functions can be contributed by
 * services tagged with `pimcore.datahub.data_importer.expression_language_provider`.
 *
 * Pipeline values can be live elements and the preview only requires read permission on the
 * configuration, so expressions are evaluated against the read-only stand-ins of the core. On top of that:
 * - `constant()` and `enum()`, which resolves its argument with `constant()`, are not registered at all,
 *   so they are rejected when the configuration is validated and cannot expose internal PHP constants
 * - all ranges of an expression can have MAX_RANGE_ITEMS items in total per evaluation, so ranges
 *   cannot be used to exhaust the memory
 *
 * @internal
 */
final class DataImporterExpressionLanguage extends ReadOnlyExpressionLanguage
{
    public const MAX_RANGE_ITEMS = 10000;

    private const UNAVAILABLE_FUNCTIONS = ['constant', 'enum'];

    private const RANGE_OPERATOR = '..';

    private readonly RangeItemBudget $rangeItemBudget;

    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     * @param iterable<ReadOnlyValueAdapterInterface> $valueAdapters
     */
    public function __construct(iterable $providers = [], iterable $valueAdapters = [])
    {
        $this->rangeItemBudget = new RangeItemBudget(self::MAX_RANGE_ITEMS);

        parent::__construct($providers, $valueAdapters);

        foreach (self::UNAVAILABLE_FUNCTIONS as $functionName) {
            unset($this->functions[$functionName]);
        }
    }

    public function evaluate(Expression|string $expression, array $values = []): mixed
    {
        $this->rangeItemBudget->reset();

        return parent::evaluate($expression, $values);
    }

    /**
     * @throws LogicException when not every range of the expression could be limited
     */
    public function parse(Expression|string $expression, array $names, int $flags = 0): ParsedExpression
    {
        $parsedExpression = parent::parse($expression, $names, $flags);

        $limitedRangeCount = 0;
        $nodes = $this->limitRanges($parsedExpression->getNodes(), $limitedRangeCount);

        if ($limitedRangeCount !== $this->countRangeOperators((string) $parsedExpression)) {
            throw new LogicException('The ranges of the expression could not be limited');
        }

        return new ParsedExpression((string) $parsedExpression, $nodes);
    }

    /**
     * Range nodes are recognised by the node structure of Symfony, which is not part of its public API.
     * The count is compared with the range operators of the lexer, so a changed structure is noticed
     * instead of leaving ranges unlimited.
     */
    private function limitRanges(Node $node, int &$limitedRangeCount): Node
    {
        foreach ($node->nodes as $key => $childNode) {
            $node->nodes[$key] = $this->limitRanges($childNode, $limitedRangeCount);
        }

        if ($node instanceof LimitedRangeNode) {
            ++$limitedRangeCount;

            return $node;
        }

        $start = $node->nodes['left'] ?? null;
        $end = $node->nodes['right'] ?? null;
        $isRange = ($node->attributes['operator'] ?? null) === self::RANGE_OPERATOR
            && $start instanceof Node
            && $end instanceof Node;

        if (!$isRange) {
            return $node;
        }

        ++$limitedRangeCount;

        return new LimitedRangeNode($start, $end, $this->rangeItemBudget);
    }

    private function countRangeOperators(string $expression): int
    {
        if (!str_contains($expression, self::RANGE_OPERATOR)) {
            return 0;
        }

        $tokenStream = (new Lexer())->tokenize($expression);
        $rangeOperatorCount = 0;

        while (!$tokenStream->isEOF()) {
            if ($tokenStream->current->test(Token::OPERATOR_TYPE, self::RANGE_OPERATOR)) {
                ++$rangeOperatorCount;
            }
            $tokenStream->next();
        }

        return $rangeOperatorCount;
    }
}
