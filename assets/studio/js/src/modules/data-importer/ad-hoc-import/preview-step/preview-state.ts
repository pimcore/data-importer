/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import {
  type BundleDataImporterAdHocImportDryRunParameters,
  type BundleDataImporterAdHocImportDryRunResponse
} from '../../data-importer-api-slice.gen'
import { type AdHocImportTarget } from '../dialog-store'
import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'
import { settingsOf } from '../file-step/file-settings'
import { fileColumn, importObjects, type ImportConfiguration, type MapWith } from '../mapping-step/configuration-state'
import { importedRows, type Mapping } from '../mapping-step/mapping-state'

export type DryRun = BundleDataImporterAdHocImportDryRunResponse
export type DryRunRow = DryRun['rows'][number]
export type DryRunParameters = BundleDataImporterAdHocImportDryRunParameters

// what happens to a row with an error: skipped, or imported without the values that cannot go into their field
export type ErrorMode = 'skip' | 'values'

export const PAGE_SIZE = 100

export interface DryRunSource {
  target: AdHocImportTarget
  inspected: InspectedFile
  objects: ObjectsSettings
  mapWith: MapWith
  configuration: ImportConfiguration | undefined
  mapping: Mapping | null
}

/**
 * What the dry run checks: the file as it is read now, how its rows find their objects, and the columns mapped here or
 * the configuration with the file's key of each column it reads.
 */
export const dryRunParameters = ({
  target,
  inspected,
  objects,
  mapWith,
  configuration,
  mapping
}: DryRunSource): DryRunParameters => {
  const chosen = importObjects(objects, mapWith, configuration, inspected)
  const parameters: DryRunParameters = {
    ...settingsOf(inspected),
    folderId: target.folderId,
    classId: chosen.classId ?? '',
    matchBy: chosen.matchBy,
    matchColumn: chosen.matchColumn ?? '',
    matchField: chosen.matchBy === 'field' ? chosen.matchField : null,
    matchLanguage: chosen.matchLanguage,
    newObjectsFolder: chosen.newObjectsFolder
  }

  if (mapWith === 'configuration' && configuration !== undefined) {
    const configurationColumns: Record<string, string> = {}
    for (const column of configuration.columns) {
      const key = fileColumn(configuration, inspected, column)
      if (key !== undefined) {
        configurationColumns[column] = key
      }
    }

    return { ...parameters, configuration: configuration.name, configurationColumns }
  }

  return {
    ...parameters,
    columns: importedRows(mapping ?? { basis: '', rows: [] }).flatMap((row) => row.choice === undefined
      ? []
      : [{
          column: row.column,
          field: row.choice.field,
          language: row.choice.language,
          ...(row.choice.key === undefined ? {} : { key: row.choice.key.id }),
          transformationPipeline: row.transformations
        }])
  }
}

export const hasErrors = (row: DryRunRow): boolean => row.action === null || row.invalid.length > 0

/**
 * Whether the import skips the row: one that means no object or that a save would refuse anyway always, one with
 * values its fields cannot take only when such rows are skipped.
 */
export const isSkipped = (row: DryRunRow, mode: ErrorMode): boolean =>
  row.action === null || row.blocked || (mode === 'skip' && row.invalid.length > 0)

export interface PreviewCounts {
  updated: number
  created: number
  withErrors: number
  // the objects the import writes
  imported: number
}

export const previewCounts = (dryRun: DryRun, mode: ErrorMode): PreviewCounts => {
  let updated = 0
  let created = 0
  let withErrors = 0
  for (const row of dryRun.rows) {
    if (hasErrors(row)) {
      withErrors += 1
    }
    if (isSkipped(row, mode)) {
      continue
    }
    if (row.action === 'create') {
      created += 1
    } else {
      updated += 1
    }
  }

  return { updated, created, withErrors, imported: updated + created }
}

export const visibleRows = (dryRun: DryRun, errorsOnly: boolean): DryRunRow[] =>
  errorsOnly ? dryRun.rows.filter(hasErrors) : dryRun.rows

export const pageOf = <T>(rows: T[], page: number, pageSize: number): T[] =>
  rows.slice((page - 1) * pageSize, page * pageSize)

/**
 * The page that still shows rows when fewer are left than before, as after "Errors only".
 */
export const lastPageWithin = (page: number, rowCount: number, pageSize: number): number =>
  Math.max(1, Math.min(page, Math.ceil(rowCount / pageSize)))

/**
 * A field's cell: its value after the import, with the one before where the import changes it. A row the import
 * cannot match, and a value its field cannot take, show what the file has.
 */
export const cellText = (row: DryRunRow, field: number): string => {
  const value = row.values[field]
  if (value === undefined) {
    return ''
  }
  const after = value.new ?? ''
  if (row.action === null || row.invalid.some((invalid) => invalid.field === field)) {
    return after
  }
  if (value.old === value.new) {
    return after
  }

  return `${value.old ?? '—'} → ${value.new ?? '—'}`
}

export type RowTone = 'error' | 'warning' | 'none'

/**
 * How a row stands out: an error where the import skips it, a warning where it leaves values out.
 */
export const rowTone = (row: DryRunRow, mode: ErrorMode): RowTone => {
  if (!hasErrors(row)) {
    return 'none'
  }

  return isSkipped(row, mode) ? 'error' : 'warning'
}
