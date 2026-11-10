/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect, useRef } from 'react'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterAdHocImportPreviewTransformationsMutation } from '../../data-importer-api-slice-enhanced'
import { awaitsFit, setTransformations, type Mapping, type TransformationStep } from './mapping-state'
import { transformationRequest, type TransformationSource } from './transformations-editor'

interface LeftColumnFits {
  mapping: Mapping | null
  source: TransformationSource | null
  // the column in the side panel, which asks for itself
  openColumn: string | undefined
  onChange: (mapping: Mapping) => void
}

// a column left before the server answered for its steps still gets that answer; without one it does not count as fitting
export const useLeftColumnFits = ({ mapping, source, openColumn, onChange }: LeftColumnFits): void => {
  const [preview] = useBundleDataImporterAdHocImportPreviewTransformationsMutation()
  // answers can arrive together, before the mapping the last one made comes back through onChange
  const latest = useRef(mapping)
  latest.current = mapping
  const asked = useRef(new Map<string, TransformationStep[]>())
  // once the step is left, the mapping can move on without this hook, and the file be read differently
  const isLeft = useRef(false)

  useEffect(() => {
    isLeft.current = false

    return () => { isLeft.current = true }
  }, [])

  useEffect(() => {
    if (mapping === null || source === null) {
      return
    }

    for (const row of mapping.rows) {
      const { column, transformations: steps } = row
      if (!awaitsFit(row) || column === openColumn || asked.current.get(column) === steps) {
        continue
      }
      asked.current.set(column, steps)

      void preview(transformationRequest(source, column, row.choice!.field, steps)).then((result) => {
        const failed = 'error' in result && result.error !== undefined
        if (failed) {
          trackError(new ApiError(result.error))
        }

        const current = latest.current
        if (isLeft.current || current?.rows.find((candidate) => candidate.column === column)?.transformations !== steps) {
          return
        }
        latest.current = setTransformations(current, column, steps, failed ? false : result.data.fits)
        onChange(latest.current)
      })
    }
  }, [mapping, source, openColumn])
}
