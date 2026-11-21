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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Model\DataObject\QuantityValue\Unit;

/**
 * Unit source settings shared by all quantity value operators.
 *
 * @internal
 */
trait QuantityValueUnitSourceTrait
{
    protected ?string $unitSource = null;

    protected ?string $staticUnitId = null;

    protected bool $unitNullIfNoValue = false;

    /**
     * Unit source used when the settings do not name one: "id", "abbr" or "static".
     */
    protected function getDefaultUnitSource(): string
    {
        return 'id';
    }

    /**
     * Whether a unit id from the input is looked up (and dropped if unknown) or passed on unchecked.
     * Passed on unchecked, an unknown id is still rejected by the quantity value model.
     */
    protected function verifiesUnitId(): bool
    {
        return true;
    }

    public function setSettings(array $settings): void
    {
        $this->unitSource = $settings['unitSourceSelect'] ?? $this->getDefaultUnitSource();
        $this->staticUnitId = isset($settings['staticUnitSelect']) ? (string) $settings['staticUnitSelect'] : null;
        $this->unitNullIfNoValue = (bool) ($settings['unitNullIfNoValueCheckbox'] ?? false);
    }

    /**
     * @return array{0: mixed, 1: mixed} value and unit id according to the unit source
     */
    protected function extractValueAndUnitId(mixed $inputData): array
    {
        $unitSource = $this->unitSource ?? $this->getDefaultUnitSource();

        if ($unitSource === 'static') {
            return [is_array($inputData) ? ($inputData[0] ?? null) : $inputData, $this->staticUnitId];
        }

        if (!is_array($inputData)) {
            return [null, null];
        }

        $unitId = null;
        if (isset($inputData[1])) {
            if ($unitSource === 'abbr') {
                $unit = Unit::getByAbbreviation($inputData[1]);
                $unitId = $unit instanceof Unit ? $unit->getId() : null;
            } elseif ($this->verifiesUnitId()) {
                $unit = Unit::getById($inputData[1]);
                $unitId = $unit instanceof Unit ? $unit->getId() : null;
            } else {
                $unitId = $inputData[1];
            }
        }

        return [$inputData[0] ?? null, $unitId];
    }

    protected function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * @return mixed the unit id, or null if the "null if no value" setting drops it
     */
    protected function applyUnitNullIfNoValue(mixed $value, mixed $unitId): mixed
    {
        return $this->unitNullIfNoValue && $this->isEmptyValue($value) ? null : $unitId;
    }
}
