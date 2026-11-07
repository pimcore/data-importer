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

// a step of the Data Importer's transformation pipeline, among those the server allows for an ad hoc import
export interface TransformationStep {
  type: string
  settings: Record<string, string>
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
  transformations: TransformationStep[]
  // the server's answer whether the transformations lead into the field; unknown until they are applied
  fits?: boolean
}

export interface Mapping {
  // what the mapping was made for; another file, class or match makes another mapping
  basis: string
  rows: MappingRow[]
}

export type RowFilter = 'all' | 'mapped' | 'open'

export const mappingBasis = (inspected: InspectedFile, objects: ObjectsSettings): string => JSON.stringify([
  inspected.fileId,
  inspected.columns,
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
      isMatch,
      transformations: []
    }
  })

  return { basis: mappingBasis(inspected, objects), rows: takeSuggestions(rows, AUTO_SCORE) }
}

const changeRow = (mapping: Mapping, column: string, change: (row: MappingRow) => MappingRow): Mapping => ({
  ...mapping,
  rows: mapping.rows.map((row) => row.column === column && !row.isMatch ? change(row) : row)
})

/**
 * A field chosen by hand is no suggestion any more, even the suggested one. The language and the transformations stay
 * only with their field: another field takes other types.
 */
export const chooseField = (mapping: Mapping, column: string, field: string | undefined): Mapping =>
  changeRow(mapping, column, (row) => {
    const same = field !== undefined && row.choice?.field === field

    return {
      ...row,
      choice: field === undefined ? undefined : { field, language: same ? row.choice!.language : null },
      auto: false,
      transformations: same ? row.transformations : [],
      fits: same ? row.fits : undefined
    }
  })

export const setTransformations = (
  mapping: Mapping,
  column: string,
  transformations: TransformationStep[],
  fits: boolean
): Mapping => changeRow(mapping, column, (row) => ({ ...row, transformations, fits }))

/**
 * Whether the column's values can go into its field: as the server answered for its transformations, or, before any
 * were applied, as the field takes a column.
 */
export const fitsField = (row: MappingRow, fields: MappingField[]): boolean => {
  if (row.isMatch || row.skipped || row.choice === undefined) {
    return true
  }
  const { field } = row.choice

  return row.fits ?? fields.find(({ key }) => key === field)?.takesColumnAsIs ?? true
}

export const unfitRows = (mapping: Mapping, fields: MappingField[]): MappingRow[] =>
  importedRows(mapping).filter((row) => !fitsField(row, fields))

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
 * Something to import beside the column that finds the objects, and every column fitting its field.
 */
export const canContinueMapping = (mapping: Mapping, fields: MappingField[]): boolean =>
  mapping.rows.some((row) => !row.isMatch && !row.skipped && row.choice !== undefined) &&
  unfitRows(mapping, fields).length === 0
