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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

/**
 * The `writeIfSourceIsEmpty` setting of the classification store data targets.
 *
 * The setting is only in force on a mapping entry that carries the opt-in marker. Without it the
 * stored value is ignored and the target keeps the behaviour it had before the setting reached it,
 * so an entry authored before this version does not change what it imports.
 *
 * @internal
 */
trait WriteIfSourceIsEmptyTrait
{
    private const SETTING_MARKER = 'writeIfSourceIsEmptyConfigured';

    private const SETTING_WRITE_IF_SOURCE_IS_EMPTY = 'writeIfSourceIsEmpty';

    private bool $writeIfSourceIsEmpty = true;

    /**
     * @param array $settings
     * @param bool $legacyDefault what an entry without the marker does, which is what this target
     *                            did before the setting reached it
     *
     * @return void
     */
    private function setWriteIfSourceIsEmptySettings(array $settings, bool $legacyDefault): void
    {
        if (!($settings[self::SETTING_MARKER] ?? false)) {
            $this->writeIfSourceIsEmpty = $legacyDefault;

            return;
        }

        $this->writeIfSourceIsEmpty = (bool) ($settings[self::SETTING_WRITE_IF_SOURCE_IS_EMPTY] ?? true);
    }

    /**
     * The empty test both classification store targets share: PHP falsiness, which is the set the
     * batch target's own `array_filter()` has always dropped. `0` counting as empty is a known
     * wart, kept because it is the migration-safe choice.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isEmptySourceValue($value): bool
    {
        return $value === null
            || $value === false
            || $value === 0
            || $value === 0.0
            || $value === ''
            || $value === '0'
            || $value === [];
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function filterEmptySourceValues(array $data): array
    {
        return array_filter($data, fn ($value): bool => !$this->isEmptySourceValue($value));
    }
}
