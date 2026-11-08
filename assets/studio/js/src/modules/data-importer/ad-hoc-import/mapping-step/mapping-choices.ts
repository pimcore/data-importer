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

// Only the suffix, so no backtracking over the name; the name before it must not be empty.
const REGIONAL_LANGUAGE = /[\s_.-][([]?([a-z]{2})[_-]([a-z]{2,4})[)\]]?$/i
const LANGUAGE = /[\s_.-][([]?([a-z]{2})[)\]]?$/i

const suffix = (pattern: RegExp, name: string): RegExpExecArray | null => {
  const match = pattern.exec(name)

  return match !== null && match.index > 0 ? match : null
}

/**
 * The language a column name ends in (`Width DE`, `Name en_GB`), read as the server reads it for its suggestions, so
 * that a column without a suggestion is known by its language too. A region unknown as a language falls back to its
 * language; spelled like a locale (`en_DE`) that fallback wins, otherwise of two languages (`de_en`) the last one.
 */
export const languageOfLabel = (label: string, languages: string[]): string | null => {
  const byLowerCase = new Map(languages.map((language) => [language.toLowerCase(), language]))
  const name = label.trim()

  let fallback: string | undefined
  const regional = suffix(REGIONAL_LANGUAGE, name)
  if (regional !== null) {
    const [, language, region] = regional
    const exact = byLowerCase.get(`${language}_${region}`.toLowerCase())
    if (exact !== undefined) {
      return exact
    }
    fallback = region.length === 2 ? byLowerCase.get(language.toLowerCase()) : undefined
    if (fallback !== undefined && language === language.toLowerCase() && region === region.toUpperCase()) {
      return fallback
    }
  }

  const plain = suffix(LANGUAGE, name)

  return (plain === null ? undefined : byLowerCase.get(plain[1].toLowerCase())) ?? fallback ?? null
}

/**
 * Gives each column going into a localized field without a language one: the language guessed from the column name,
 * else the first no other column has for that field. A column picked by hand then takes the first language; a
 * suggestion no language is left for is not taken.
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
    const guessed = row.suggestion?.language ?? languageOfLabel(row.label, languages)
    const language = guessed !== null && free.includes(guessed) ? guessed : free[0]
    if (language === undefined && row.auto) {
      rows[position] = { ...row, choice: undefined, auto: false }
      return
    }
    rows[position] = { ...row, choice: { ...choice, language: language ?? languages[0] } }
  })

  return { ...mapping, rows }
}
