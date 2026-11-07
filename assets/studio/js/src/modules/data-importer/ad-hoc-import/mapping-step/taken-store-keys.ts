/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type MappingField, type MappingRow } from './mapping-state'

/**
 * The keys of the store other columns than this one already go into; of a localized store, those they hold in every
 * language.
 */
export const takenStoreKeys = (
  rows: MappingRow[],
  store: Pick<MappingField, 'key' | 'localized'>,
  column: string,
  languages: string[]
): string[] => {
  const held = new Map<string, Set<string | null>>()
  rows.forEach(({ column: other, skipped, choice }) => {
    if (other !== column && !skipped && choice?.field === store.key && choice.key !== undefined) {
      held.set(choice.key.id, (held.get(choice.key.id) ?? new Set()).add(choice.language))
    }
  })

  return [...held]
    .filter(([, inLanguages]) => !store.localized || languages.every((language) => inLanguages.has(language)))
    .map(([id]) => id)
}
