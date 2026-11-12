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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final readonly class RowMatch
{
    /**
     * @param ElementInterface|null $element the object to apply the mapping to, not saved; null for an error row
     * @param list<int> $objectIds the objects an error row refers to
     * @param int|null $firstRow the earlier row of the file a duplicate or a taken key refers to
     */
    public function __construct(
        public RowMatchOutcome $outcome,
        public string $matchValue,
        public ?ElementInterface $element = null,
        public array $objectIds = [],
        public ?int $firstRow = null,
    ) {
    }

    public function isError(): bool
    {
        return $this->element === null;
    }
}
