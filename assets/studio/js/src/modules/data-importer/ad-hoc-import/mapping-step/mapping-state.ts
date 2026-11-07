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

export type LoadedMapping = BundleDataImporterAdHocImportMappingResponse
export type MappingField = LoadedMapping['fields'][number]

// A suggestion this sure is taken at once; a weaker one waits for "Use all suggestions"
export const AUTO_SCORE = 100

export interface FieldChoice {
  field: string
  // for a localized field: the language the column holds
  language: string | null
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

const sameChoice = (a: FieldChoice, b: FieldChoice): boolean => a.field === b.field && a.language === b.language

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

export interface TakenFields {
  // whether another column than this one already goes into the field
  has: (choice: FieldChoice, column?: string) => boolean
}

const choiceKey = ({ field, language }: FieldChoice): string => JSON.stringify([field, language])

/**
 * Which columns go into which field, built once per mapping so that each field option is looked up, not searched.
 */
export const takenFields = (rows: MappingRow[]): TakenFields => {
  const columnsOf = new Map<string, string[]>()
  for (const row of rows) {
    if (!row.skipped && row.choice !== undefined) {
      const key = choiceKey(row.choice)
      columnsOf.set(key, [...(columnsOf.get(key) ?? []), row.column])
    }
  }

  return {
    has: (choice, column) => (columnsOf.get(choiceKey(choice)) ?? []).some((other) => other !== column)
  }
}

/**
 * Whether another column than this one already goes into the field; one column per field, and per language for a
 * localized one.
 */
export const isTaken = (rows: MappingRow[], choice: FieldChoice, column?: string): boolean =>
  rows.some((row) => row.column !== column && !row.skipped && row.choice !== undefined && sameChoice(row.choice, choice))

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
