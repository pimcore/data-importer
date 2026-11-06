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

namespace Pimcore\Bundle\DataImporterBundle\Exception;

use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingIssue;

/**
 * Thrown when a mapping item fails while a mapping is applied without saving.
 */
final class MappingApplicationException extends \Exception
{
    /**
     * @param list<MappingIssue> $warnings
     */
    public function __construct(
        private readonly int|string $itemIndex,
        private readonly string $itemLabel,
        \Throwable $previous,
        private readonly array $warnings = [],
    ) {
        parent::__construct(
            sprintf('Mapping item %s (`%s`) failed: %s', $itemIndex, $itemLabel, $previous->getMessage()),
            0,
            $previous
        );
    }

    public function getItemIndex(): int|string
    {
        return $this->itemIndex;
    }

    public function getItemLabel(): string
    {
        return $this->itemLabel;
    }

    /**
     * The warnings of the items applied so far, including the failing one, as apply() would have returned them.
     *
     * @return list<MappingIssue>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
