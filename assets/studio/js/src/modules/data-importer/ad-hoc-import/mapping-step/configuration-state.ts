/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type BundleDataImporterAdHocImportConfigurationsResponse } from '../../data-importer-api-slice.gen'
import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'

export type ImportConfiguration = BundleDataImporterAdHocImportConfigurationsResponse['configurations'][number]

export type MapWith = 'here' | 'configuration'

// the one reason only the file shows; the others come from the server
export const MATCH_COLUMN_MISSING = 'match-column'

export interface ImportObjects extends ObjectsSettings {
  // for a localized field matched by: its language
  matchLanguage: string | null
}

const POSITION = /^(0|[1-9]\d*)$/

// a CSV with a header row keys its columns by name; an XLSX, or a CSV without one, by position
const keysByName = (inspected: InspectedFile): boolean => inspected.type === 'csv' && inspected.hasHeaderRow

const hasKey = (inspected: InspectedFile, column: string): boolean =>
  inspected.columns.some(({ dataIndex }) => dataIndex === column)

/**
 * The file's key of a column the configuration reads, or undefined if the file lacks it. The two may key columns
 * differently, so a name is found by the file's header and a position by the column's place.
 */
export const fileColumn = (
  configuration: ImportConfiguration,
  inspected: InspectedFile,
  column: string
): string | undefined => {
  if ((configuration.columnsBy === 'name') === keysByName(inspected)) {
    return hasKey(inspected, column) ? column : undefined
  }

  if (configuration.columnsBy === 'name') {
    return inspected.hasHeaderRow
      ? inspected.columns.find(({ label }) => label === column.trim())?.dataIndex
      : undefined
  }

  // a header row may leave a column unnamed, so the file's columns are found by their place, not their index
  return POSITION.test(column)
    ? inspected.columns.find(({ position }) => position === Number(column))?.dataIndex
    : undefined
}

export const columnLabel = (inspected: InspectedFile, column: string): string =>
  inspected.columns.find(({ dataIndex }) => dataIndex === column)?.label ?? column

/**
 * How the step names a column the configuration reads: by its label in the file, or as the configuration names it.
 */
export const configurationColumnLabel = (
  configuration: ImportConfiguration,
  inspected: InspectedFile | undefined,
  column: string
): string => {
  const key = inspected === undefined ? undefined : fileColumn(configuration, inspected, column)

  return inspected === undefined || key === undefined ? column : columnLabel(inspected, key)
}

/**
 * Why the import cannot use the configuration with this file, or null if it can.
 */
export const unusableReason = (configuration: ImportConfiguration, inspected: InspectedFile): string | null => {
  if (configuration.unusable !== null) {
    return configuration.unusable
  }

  return configuration.matchColumn !== null && fileColumn(configuration, inspected, configuration.matchColumn) === undefined
    ? MATCH_COLUMN_MISSING
    : null
}

/**
 * The columns the configuration reads that the file does not have; it imports nothing from them.
 */
export const missingColumns = (configuration: ImportConfiguration, inspected: InspectedFile): string[] =>
  configuration.columns.filter((column) => fileColumn(configuration, inspected, column) === undefined)

export const findConfiguration = (
  configurations: ImportConfiguration[] | undefined,
  name: string | undefined
): ImportConfiguration | undefined => configurations?.find((configuration) => configuration.name === name)

/**
 * What the import works with: a chosen configuration's class, match and folder replace those of the file step.
 */
export const importObjects = (
  objects: ObjectsSettings,
  mapWith: MapWith,
  configuration: ImportConfiguration | undefined,
  inspected: InspectedFile | undefined
): ImportObjects => {
  if (mapWith === 'here' || configuration === undefined || inspected === undefined) {
    return { ...objects, matchLanguage: null }
  }

  return {
    classId: configuration.classId ?? undefined,
    matchBy: configuration.matchBy === 'id' ? 'id' : 'field',
    matchField: configuration.matchField ?? undefined,
    matchLanguage: configuration.matchLanguage,
    // the file's key: the import reads the file's rows
    matchColumn: configuration.matchColumn === null
      ? undefined
      : fileColumn(configuration, inspected, configuration.matchColumn),
    newObjectsFolder: configuration.newObjectsFolder
  }
}

/**
 * A configuration is chosen and the import can use it with this file.
 */
export const canContinueWithConfiguration = (
  configuration: ImportConfiguration | undefined,
  inspected: InspectedFile | undefined
): boolean => configuration !== undefined && inspected !== undefined && unusableReason(configuration, inspected) === null
