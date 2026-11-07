/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type BundleDataImporterAdHocImportMappingResponse } from '../../data-importer-api-slice.gen'
import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'
import { isTaken } from './taken-fields'

export type LoadedMapping = BundleDataImporterAdHocImportMappingResponse
export type MappingField = LoadedMapping['fields'][number]

// A suggestion this sure is taken at once; a weaker one waits for "Use all suggestions"
export const AUTO_SCORE = 100

// a key of a classification store, as `<group id>-<key id>`
export interface StoreKey {
  id: string
  label: string
}

export interface FieldChoice {
  field: string
  // for a localized field: the language the column holds
  language: string | null
  // for a classification store: the key the column goes into
  key?: StoreKey
}

export interface MappingRow {
  column: string
  label: string
  // the first value of the first rows, to tell what the column holds
  sample?: string
  choice?: FieldChoice
  // the choice is a suggestion no one has changed
  auto: boolean
  // "Don't import": the column is in the area to restore it from
  skipped: boolean
  suggestion?: FieldChoice & { score: number }
  // the column finds the objects; its field is the one they are matched by, or none for the object ID
  isMatch: boolean
}

export interface Mapping {
  // what the mapping was made for; another file, class or match makes another mapping
  basis: string
  rows: MappingRow[]
}

export type RowFilter = 'all' | 'mapped' | 'open'

/**
 * Columns are known as the file step knows them (keepMatchColumn): by their names, so the same names on another sheet
 * keep the mapping; without a header row only by their position, so another sheet or way of splitting the rows is
 * another mapping.
 */
export const mappingBasis = (inspected: InspectedFile, objects: ObjectsSettings): string => JSON.stringify([
  inspected.fileId,
  inspected.columns,
  inspected.hasHeaderRow,
  inspected.hasHeaderRow
    ? null
    : [inspected.sheetName ?? null, inspected.delimiter ?? null, inspected.enclosure ?? null, inspected.escape ?? null],
  objects.classId,
  objects.matchBy,
  objects.matchBy === 'field' ? objects.matchField : null,
  objects.matchColumn
])

const sampleOf = (inspected: InspectedFile, position: number): string | undefined =>
  inspected.firstRows
    .map((row) => row[position])
    .find((value): value is string => value !== null && value !== undefined && value !== '')

/**
 * The same columns read again, from another sheet say: the choices stay, the first values are the ones read now.
 */
export const withSamples = (mapping: Mapping, inspected: InspectedFile): Mapping => {
  const positionOf = new Map(inspected.columns.map(({ dataIndex }, position) => [dataIndex, position]))

  return {
    ...mapping,
    rows: mapping.rows.map((row) => {
      const position = positionOf.get(row.column)

      return { ...row, sample: position === undefined ? undefined : sampleOf(inspected, position) }
    })
  }
}

const isOpen = (row: MappingRow): boolean => !row.isMatch && !row.skipped && row.choice === undefined

/**
 * Takes the suggestions of the open columns, the surest first, each as long as no other column has its field.
 */
const takeSuggestions = (rows: MappingRow[], minScore: number): MappingRow[] => {
  const next = [...rows]
  const surestFirst = next
    .map((row, position) => ({ row, position }))
    .filter(({ row }) => isOpen(row) && row.suggestion !== undefined && row.suggestion.score >= minScore)
    .sort((a, b) => b.row.suggestion!.score - a.row.suggestion!.score)

  for (const { row, position } of surestFirst) {
    const { field, language } = row.suggestion!
    if (!isTaken(next, { field, language })) {
      next[position] = { ...row, choice: { field, language }, auto: true }
    }
  }

  return next
}

export const createMapping = (
  inspected: InspectedFile,
  objects: ObjectsSettings,
  { fields, suggestions }: LoadedMapping
): Mapping => {
  const known = new Set(fields.map(({ key }) => key))
  const suggestionOf = new Map(suggestions
    .filter(({ targetFieldName }) => known.has(targetFieldName))
    .map(({ sourceIndex, targetFieldName, language, score }) => [sourceIndex, { field: targetFieldName, language, score }]))

  const rows = inspected.columns.map(({ dataIndex, label }, position): MappingRow => {
    const isMatch = dataIndex === objects.matchColumn

    return {
      column: dataIndex,
      label,
      sample: sampleOf(inspected, position),
      choice: isMatch && objects.matchBy === 'field' && objects.matchField !== undefined
        ? { field: objects.matchField, language: null }
        : undefined,
      auto: false,
      skipped: false,
      suggestion: isMatch ? undefined : suggestionOf.get(dataIndex),
      isMatch
    }
  })

  return { basis: mappingBasis(inspected, objects), rows: takeSuggestions(rows, AUTO_SCORE) }
}

const changeRow = (mapping: Mapping, column: string, change: (row: MappingRow) => MappingRow): Mapping => ({
  ...mapping,
  rows: mapping.rows.map((row) => row.column === column && !row.isMatch ? change(row) : row)
})

/**
 * A field chosen by hand is no suggestion any more, even the suggested one. The language stays only with its field.
 */
export const chooseField = (mapping: Mapping, column: string, field: string | undefined): Mapping =>
  changeRow(mapping, column, (row) => ({
    ...row,
    choice: field === undefined
      ? undefined
      : { field, language: row.choice?.field === field ? row.choice.language : null },
    auto: false
  }))

/**
 * A classification store goes in by one of its keys, one column per key (and language); the language stays while the
 * store does, unless another column holds the key in it: then the first language free for the key.
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

    return language === undefined
      ? row
      : { ...row, choice: { field, language, key }, auto: false }
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

export const skipColumn = (mapping: Mapping, column: string): Mapping =>
  changeRow(mapping, column, (row) => ({ ...row, choice: undefined, auto: false, skipped: true }))

export const restoreColumn = (mapping: Mapping, column: string): Mapping =>
  changeRow(mapping, column, (row) => ({ ...row, skipped: false }))

export const takeAllSuggestions = (mapping: Mapping): Mapping => ({ ...mapping, rows: takeSuggestions(mapping.rows, 0) })

export const pendingSuggestionCount = (mapping: Mapping): number => {
  const taken = takeAllSuggestions(mapping).rows

  return taken.filter((row, position) => row.choice !== mapping.rows[position].choice).length
}

export const importedRows = (mapping: Mapping): MappingRow[] => mapping.rows.filter(({ skipped }) => !skipped)

export const skippedRows = (mapping: Mapping): MappingRow[] => mapping.rows.filter(({ skipped }) => skipped)

const matchesFilter = (row: MappingRow, filter: RowFilter): boolean => {
  switch (filter) {
    case 'mapped':
      return row.isMatch || row.choice !== undefined
    case 'open':
      return isOpen(row)
    default:
      return true
  }
}

export const rowCounts = (mapping: Mapping): Record<RowFilter, number> => {
  const rows = importedRows(mapping)

  return {
    all: rows.length,
    mapped: rows.filter((row) => matchesFilter(row, 'mapped')).length,
    open: rows.filter((row) => matchesFilter(row, 'open')).length
  }
}

export const visibleRows = (mapping: Mapping, filter: RowFilter, search: string): MappingRow[] => {
  const term = search.trim().toLowerCase()

  return importedRows(mapping).filter((row) => matchesFilter(row, filter) &&
    (term === '' || row.label.toLowerCase().includes(term)))
}

/**
 * The fields the class requires that no column goes into.
 */
export const missingRequiredFields = (mapping: Mapping, fields: MappingField[]): MappingField[] => {
  const chosen = new Set(importedRows(mapping).flatMap(({ choice }) => choice === undefined ? [] : [choice.field]))

  return fields.filter(({ key, mandatory }) => mandatory && !chosen.has(key))
}

/**
 * Something to import beside the column that finds the objects.
 */
export const canContinueMapping = (mapping: Mapping): boolean =>
  mapping.rows.some((row) => !row.isMatch && !row.skipped && row.choice !== undefined)
