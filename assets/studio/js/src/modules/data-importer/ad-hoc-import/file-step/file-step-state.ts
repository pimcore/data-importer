/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type BundleDataImporterAdHocImportFileResponse } from '../../data-importer-api-slice.gen'

export type InspectedFile = BundleDataImporterAdHocImportFileResponse

export interface FileInfo {
  name: string
  size: number
}

export type FilePhase =
  | { kind: 'empty' }
  | { kind: 'uploading', file: FileInfo, loaded: number, total: number }
  | { kind: 'inspected', file: FileInfo, inspected: InspectedFile }

export type MatchBy = 'id' | 'field'

export interface ObjectsSettings {
  classId?: string
  matchBy: MatchBy
  matchField?: string
  matchColumn?: string
  // the path of a folder; null: the folder the import started from
  newObjectsFolder: string | null
}

export const initialObjects: ObjectsSettings = { matchBy: 'field', newObjectsFolder: null }

export const ACCEPTED_EXTENSIONS = ['.xlsx', '.csv']

export const hasAcceptedExtension = (name: string): boolean =>
  ACCEPTED_EXTENSIONS.some((extension) => name.toLowerCase().endsWith(extension))

export const refusedForFormat = (): InspectedFile => ({
  maxRows: 0,
  refusal: 'wrong-format',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 0,
  columns: [],
  firstRows: []
})

/**
 * A column chosen for matching stays only while the file still has it. XLSX columns and columns without a header are
 * known by their position, so the same dataIndex can be another column: it has to keep its name, and without a header
 * there is no name, so only an unchanged sheet or way of splitting the rows tells it is still the same column.
 */
export const keepMatchColumn = (objects: ObjectsSettings, before: InspectedFile, inspected: InspectedFile): ObjectsSettings => {
  if (objects.matchColumn === undefined) {
    return objects
  }

  const chosenIn = ({ columns }: InspectedFile): string | undefined =>
    columns.find(({ dataIndex }) => dataIndex === objects.matchColumn)?.label

  const splitsAlike = inspected.sheetName === before.sheetName &&
    inspected.delimiter === before.delimiter &&
    inspected.enclosure === before.enclosure &&
    inspected.escape === before.escape

  const isSameColumn = chosenIn(inspected) !== undefined &&
    chosenIn(inspected) === chosenIn(before) &&
    (inspected.hasHeaderRow || splitsAlike)

  return isSameColumn ? objects : { ...objects, matchColumn: undefined }
}

/**
 * What the next steps need: an accepted file with rows, a class and what to match by.
 */
export const canContinue = (phase: FilePhase, objects: ObjectsSettings): boolean => {
  if (phase.kind !== 'inspected') {
    return false
  }

  const { inspected } = phase

  return inspected.fileId !== undefined && inspected.fileId !== null &&
    (inspected.refusal === undefined || inspected.refusal === null) &&
    inspected.rowCount > 0 &&
    objects.classId !== undefined &&
    objects.matchColumn !== undefined &&
    (objects.matchBy === 'id' || objects.matchField !== undefined)
}

export const fileBadge = (phase: FilePhase & { kind: 'uploading' | 'inspected' }): string =>
  (phase.file.name.split('.').pop() ?? '').toUpperCase().slice(0, 4)
