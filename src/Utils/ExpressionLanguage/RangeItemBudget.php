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

/**
 * Number of range items one evaluation of an expression may still create. It is shared by all range
 * nodes of an expression language, which resets it before every evaluation.
 *
 * @internal
 */
final class RangeItemBudget
{
    private int $remainingItems;

    public function __construct(private readonly int $maxItems)
    {
        $this->remainingItems = $maxItems;
    }

    public function reset(): void
    {
        $this->remainingItems = $this->maxItems;
    }

    /**
     * @throws RangeLimitExceededException
     */
    public function assertAvailable(int|float $items): void
    {
        // Negated so that NAN, which compares false with everything, is rejected as well.
        if (!($items <= $this->remainingItems)) {
            throw new RangeLimitExceededException($this->maxItems);
        }
    }

    /**
     * @throws RangeLimitExceededException
     */
    public function consume(int $items): void
    {
        $this->assertAvailable($items);
        $this->remainingItems -= $items;
    }
}
