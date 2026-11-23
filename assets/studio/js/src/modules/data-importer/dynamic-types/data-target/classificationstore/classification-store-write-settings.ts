/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type DataTargetConfig } from '../../../types'

type DataTargetSettings = DataTargetConfig['settings']

/**
 * Marker telling the backend that `writeIfSourceIsEmpty` has been decided for this mapping entry
 * and is therefore in force. Without it both classification store targets keep the behaviour they
 * had before the setting reached them, so an entry authored before this version does not change
 * what it imports.
 */
export const WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED = 'writeIfSourceIsEmptyConfigured'

/** What a classification store target without the marker does with an empty source value. */
export const LEGACY_WRITE_IF_SOURCE_IS_EMPTY = {
  classificationstore: true,
  classificationstoreBatch: false
} as const

export type ClassificationStoreTargetId = keyof typeof LEGACY_WRITE_IF_SOURCE_IS_EMPTY

/**
 * What the target actually does today, which is what the control has to show: while the marker is
 * absent the stored value is not in force, so showing it would state the opposite of the truth on
 * a batch entry that is in fact dropping empty values.
 */
export const resolveWriteIfSourceIsEmpty = (
  settings: DataTargetSettings,
  target: ClassificationStoreTargetId
): boolean => {
  if (settings?.[WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED] !== true) {
    return LEGACY_WRITE_IF_SOURCE_IS_EMPTY[target]
  }

  return settings?.writeIfSourceIsEmpty ?? true
}

/**
 * The settings a change of the control writes: the new value plus the marker, so that changing it
 * takes effect on an entry authored before this version.
 */
export const withWriteIfSourceIsEmpty = (
  settings: DataTargetSettings,
  writeIfSourceIsEmpty: boolean
): DataTargetSettings => ({
  ...settings,
  [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true,
  writeIfSourceIsEmpty
})

/**
 * The marker and the effective value a target-type change stores, so that selecting either
 * classification store target also puts the entry under the control.
 */
export const defaultWriteSettings = (
  settings: DataTargetSettings,
  target: ClassificationStoreTargetId
): DataTargetSettings => withWriteIfSourceIsEmpty(settings, resolveWriteIfSourceIsEmpty(settings, target))
