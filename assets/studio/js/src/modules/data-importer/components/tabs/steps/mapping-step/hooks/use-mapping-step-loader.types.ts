/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type MappingConfigItem, type ClassAttribute } from '../../../../../types'
import { type SourceRow } from '../sources-panel/sources-panel'

export interface ColumnHeaderEntry {
  id?: string
  dataIndex?: string
  label?: string
}

export interface UseMappingStepLoaderResult {
  columnHeaderOptions: Array<{ value: string, label: string }>
  initialLoadDone: boolean
  sourceRows: SourceRow[]
  hasPreviewError: boolean
  attributesMap: Record<string, ClassAttribute[]>
  setAttributesMap: React.Dispatch<React.SetStateAction<Record<string, ClassAttribute[]>>>
  classId: string | undefined
  mappingTrtList: string[] | undefined
  getMappingConfig: () => MappingConfigItem[]
}

export function parseClassAttribute (raw: object): ClassAttribute {
  const obj = raw as Record<string, any>
  return {
    key: obj.key ?? obj.name ?? '',
    title: obj.title ?? obj.name ?? obj.key ?? '',
    localized: Boolean(obj.localized ?? false)
  }
}
