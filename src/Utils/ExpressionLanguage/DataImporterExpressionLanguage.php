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

use Pimcore\Helper\SymfonyExpression\ReadOnlyExpressionLanguage;
use Pimcore\Helper\SymfonyExpression\ReadOnlyValueAdapterInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * Expression language used by the symfony expression operator. Functions can be contributed by
 * services tagged with `pimcore.datahub.data_importer.expression_language_provider`.
 *
 * Pipeline values can be live elements and the preview only requires read permission on the
 * configuration, so expressions are evaluated against the read-only stand-ins of the core. On top of that:
 * - `constant()` and `enum()`, which resolves its argument with `constant()`, are not registered at all,
 *   so they are rejected when the configuration is validated and cannot expose internal PHP constants
 * - ranges are limited in size
 *
 * @internal
 */
final class DataImporterExpressionLanguage extends ReadOnlyExpressionLanguage
{
    private const UNAVAILABLE_FUNCTIONS = ['constant', 'enum'];

    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     * @param iterable<ReadOnlyValueAdapterInterface> $valueAdapters
     */
    public function __construct(iterable $providers = [], iterable $valueAdapters = [])
    {
        parent::__construct($providers, $valueAdapters);

        foreach (self::UNAVAILABLE_FUNCTIONS as $functionName) {
            unset($this->functions[$functionName]);
        }
    }

    public function parse(Expression|string $expression, array $names, int $flags = 0): ParsedExpression
    {
        $parsedExpression = parent::parse($expression, $names, $flags);

        return new ParsedExpression((string) $parsedExpression, $this->limitRanges($parsedExpression->getNodes()));
    }

    private function limitRanges(Node $node): Node
    {
        foreach ($node->nodes as $key => $childNode) {
            $node->nodes[$key] = $this->limitRanges($childNode);
        }

        $isUnlimitedRange = $node instanceof BinaryNode
            && !$node instanceof LimitedRangeNode
            && $node->attributes['operator'] === LimitedRangeNode::OPERATOR;

        return $isUnlimitedRange ? new LimitedRangeNode($node->nodes['left'], $node->nodes['right']) : $node;
    }
}
