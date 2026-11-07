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

const columnsOf = (inspected: InspectedFile): Set<string> => new Set(inspected.columns.map(({ dataIndex }) => dataIndex))

export const columnLabel = (inspected: InspectedFile, column: string): string =>
  inspected.columns.find(({ dataIndex }) => dataIndex === column)?.label ?? column

/**
 * Why the import cannot use the configuration with this file, or null if it can.
 */
export const unusableReason = (configuration: ImportConfiguration, inspected: InspectedFile): string | null => {
  if (configuration.unusable !== null) {
    return configuration.unusable
  }

  return configuration.matchColumn !== null && !columnsOf(inspected).has(configuration.matchColumn)
    ? MATCH_COLUMN_MISSING
    : null
}

/**
 * The columns the configuration reads that the file does not have; it imports nothing from them.
 */
export const missingColumns = (configuration: ImportConfiguration, inspected: InspectedFile): string[] => {
  const columns = columnsOf(inspected)

  return configuration.columns.filter((column) => !columns.has(column))
}

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
  configuration: ImportConfiguration | undefined
): ImportObjects => {
  if (mapWith === 'here' || configuration === undefined) {
    return { ...objects, matchLanguage: null }
  }

  return {
    classId: configuration.classId ?? undefined,
    matchBy: configuration.matchBy === 'id' ? 'id' : 'field',
    matchField: configuration.matchField ?? undefined,
    matchLanguage: configuration.matchLanguage,
    matchColumn: configuration.matchColumn ?? undefined,
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
