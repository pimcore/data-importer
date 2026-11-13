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

use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * The range operator (`1..10`) with an upper limit on the number of items, so an expression cannot
 * exhaust the memory with a range like `1..999999999`.
 *
 * @internal
 */
final class LimitedRangeNode extends BinaryNode
{
    public const OPERATOR = '..';

    public const MAX_ITEMS = 10000;

    public function __construct(Node $start, Node $end)
    {
        parent::__construct(self::OPERATOR, $start, $end);
    }

    /**
     * @throws SyntaxError when the range has more than MAX_ITEMS items
     */
    public function evaluate(array $functions, array $values): array
    {
        $start = $this->nodes['left']->evaluate($functions, $values);
        $end = $this->nodes['right']->evaluate($functions, $values);

        if (abs($this->toNumber($end) - $this->toNumber($start)) >= self::MAX_ITEMS) {
            throw new SyntaxError(sprintf('A range can have %d items at most', self::MAX_ITEMS));
        }

        return range($start, $end);
    }

    /**
     * range() starts or ends at 0 for a bound that is not numeric, unless both bounds are characters.
     */
    private function toNumber(mixed $bound): float
    {
        return is_numeric($bound) ? (float) $bound : 0.0;
    }
}
