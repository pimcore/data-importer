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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

/**
 * The lower and the upper bound of a range, read from the first two sources of the row. Shared by
 * the range operators, which differ only in what they build out of the two numbers.
 *
 * @internal
 */
trait RangeBoundsTrait
{
    /**
     * @return array{0: float|null, 1: float|null} the lower and the upper bound, each null where
     *                                             the row carries none - a half-open range is a
     *                                             range, and an absent bound is not zero
     */
    protected function extractBounds(mixed $inputData): array
    {
        $values = is_array($inputData) ? array_values($inputData) : [$inputData];

        return [$this->bound($values[0] ?? null), $this->bound($values[1] ?? null)];
    }

    protected function renderBounds(mixed $minimum, mixed $maximum): string
    {
        return sprintf('%s - %s', $minimum ?? '', $maximum ?? '');
    }

    /**
     * A range is two numbers, so it is built from several sources and never from one column.
     *
     * @throws InvalidConfigurationException
     */
    protected function assertBoundsInput(string $inputType, string $operatorLabel, ?int $index): void
    {
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for %s operator at transformation position %s",
                $inputType,
                $operatorLabel,
                $index
            ));
        }
    }

    private function bound(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
