/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { uuid } from '@pimcore/studio-ui-bundle/utils'
import type { MappingConfigItem } from '../../../../../types'
import type { BundleDataImporterMappingSuggestionsResponse } from '../../../../../data-importer-api-slice.gen'
import type { SourceRow } from '../sources-panel/sources-panel'

export interface MappingSuggestion {
  id: string
  sourceIndex: string
  sourceLabel: string
  targetFieldName: string
  targetFieldLabel: string
  transformationResultType: string
  score: number
  language: string | null
  source: 'name' | 'ai'
  previewResult: string | null
}

export function collectUnmappedColumns (
  columnHeaderOptions: Array<{ value: string, label: string }>,
  existingMappings: MappingConfigItem[]
): Array<{ index: string, label: string }> {
  const usedIndices = new Set<string>()
  existingMappings.forEach((item) => {
    (item.dataSourceIndex ?? []).forEach((index) => usedIndices.add(index))
  })

  return columnHeaderOptions
    .filter((column) => !usedIndices.has(column.value))
    .map((column) => ({ index: column.value, label: column.label }))
}

export function toMappingSuggestions (
  suggestions: BundleDataImporterMappingSuggestionsResponse['suggestions'],
  sourceRows: SourceRow[]
): MappingSuggestion[] {
  return suggestions.map((suggestion) => ({
    ...suggestion,
    id: uuid(),
    previewResult: sourceRows.find((row) => row.dataIndex === suggestion.sourceIndex)?.value ?? null
  }))
}
