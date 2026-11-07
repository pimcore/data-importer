/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { changeRow, type Mapping, type MappingField, type MappingRow, type StoreKey } from './mapping-state'
import { isTaken } from './taken-fields'

/**
 * A classification store goes in by one of its keys, one column per key (and language); the language stays while the
 * store does, unless another column holds the key in it: then the first language free for the key. Transformations
 * stay only with the same key.
 */
export const chooseStoreKey = (
  mapping: Mapping,
  column: string,
  field: string,
  key: StoreKey,
  languages: string[] = []
): Mapping =>
  changeRow(mapping, column, (row) => {
    const free = (language: string | null): boolean => !isTaken(mapping.rows, { field, language, key }, column)
    const kept = row.choice?.field === field ? row.choice.language : null
    const language = free(kept) ? kept : kept === null ? undefined : languages.find(free)
    const same = row.choice?.field === field && row.choice.key?.id === key.id

    return language === undefined
      ? row
      : {
          ...row,
          choice: { field, language, key },
          auto: false,
          transformations: same ? row.transformations : [],
          fits: same ? row.fits : undefined
        }
  })

const languageTaken = (rows: MappingRow[], row: MappingRow, language: string): boolean =>
  row.choice !== undefined && isTaken(rows, { ...row.choice, language }, row.column)

/**
 * The languages the column can hold: its own and those no other column has for its field (and key).
 */
export const freeLanguages = (rows: MappingRow[], row: MappingRow, languages: string[]): string[] =>
  languages.filter((language) => language === row.choice?.language || !languageTaken(rows, row, language))

/**
 * A language another column already has for the field (and key) is refused.
 */
export const chooseLanguage = (mapping: Mapping, column: string, language: string): Mapping =>
  changeRow(mapping, column, (row) => row.choice === undefined || languageTaken(mapping.rows, row, language)
    ? row
    : { ...row, choice: { ...row.choice, language } })

/**
 * Gives each column going into a localized field without a language one: the language guessed from the column name,
 * else the first no other column has for that field, else the first.
 */
export const withLanguages = (mapping: Mapping, fields: MappingField[], languages: string[]): Mapping => {
  const localized = new Set(fields.filter(({ localized }) => localized).map(({ key }) => key))
  const needsLanguage = (row: MappingRow): boolean =>
    !row.isMatch && !row.skipped && row.choice?.language === null && localized.has(row.choice.field)
  if (languages.length === 0 || !mapping.rows.some(needsLanguage)) {
    return mapping
  }

  const rows = [...mapping.rows]
  rows.forEach((row, position) => {
    if (!needsLanguage(row)) {
      return
    }
    const choice = row.choice!
    const free = languages.filter((language) => !isTaken(rows, { ...choice, language }, row.column))
    const guessed = row.suggestion?.language ?? null
    const language = guessed !== null && free.includes(guessed) ? guessed : free[0] ?? languages[0]
    rows[position] = { ...row, choice: { ...choice, language } }
  })

  return { ...mapping, rows }
}
