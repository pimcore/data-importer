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
use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\ExpressionLanguage\Node\Node;

/**
 * The range operator (`1..10`), drawing its items from a budget that is shared by all ranges of an
 * expression. A single huge range like `1..999999999` is rejected before it is created, many
 * smaller ones as soon as they exceed the budget together.
 *
 * @internal
 */
final class LimitedRangeNode extends Node
{
    private const START = 'start';

    private const END = 'end';

    public function __construct(Node $start, Node $end, private readonly RangeItemBudget $itemBudget)
    {
        parent::__construct([self::START => $start, self::END => $end]);
    }

    /**
     * @throws RangeLimitExceededException when the ranges of the evaluation exceed the item budget
     */
    public function evaluate(array $functions, array $values): array
    {
        $start = $this->nodes[self::START]->evaluate($functions, $values);
        $end = $this->nodes[self::END]->evaluate($functions, $values);

        $this->itemBudget->assertAvailable(abs($this->toNumber($end) - $this->toNumber($start)) + 1);

        $items = range($start, $end);
        $this->itemBudget->consume(count($items));

        return $items;
    }

    /**
     * @throws LogicException
     */
    public function compile(Compiler $compiler): void
    {
        throw new LogicException('Limited ranges cannot be compiled, they are only evaluated');
    }

    public function toArray(): array
    {
        return ['(', $this->nodes[self::START], ' .. ', $this->nodes[self::END], ')'];
    }

    /**
     * range() starts or ends at 0 for a bound that is not numeric, unless both bounds are characters.
     */
    private function toNumber(mixed $bound): float
    {
        return is_numeric($bound) ? (float) $bound : 0.0;
    }
}
