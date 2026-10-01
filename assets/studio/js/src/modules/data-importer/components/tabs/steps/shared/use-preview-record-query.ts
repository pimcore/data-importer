/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { type BackendConfiguration } from '../../../../utils/transformers'
import { type PreviewRequest, useMappingSource } from '../mapping-source/mapping-source'

interface UsePreviewRecordQueryParams {
  enabled: boolean
  getCurrentConfig?: () => BackendConfiguration
  /** When this counter increments, the current record is re-fetched without resetting the record index. */
  forceRefreshToken?: number
}

interface LoadOptions {
  forceRefetch?: boolean
}

export interface UsePreviewRecordQueryResult {
  dataPreview: Array<Record<string, any>>
  currentRecordIndex: number
  isLoading: boolean
  isFetching: boolean
  isError: boolean
  error: unknown
  load: (recordNumber: number, options?: LoadOptions) => void
}

export function usePreviewRecordQuery ({
  enabled,
  getCurrentConfig,
  forceRefreshToken
}: UsePreviewRecordQueryParams): UsePreviewRecordQueryResult {
  const source = useMappingSource()
  const [request, setRequest] = useState<PreviewRequest | undefined>(undefined)
  const [requestedRecordIndex, setRequestedRecordIndex] = useState(0)
  const [shouldForceRefetch, setShouldForceRefetch] = useState(false)

  const {
    data,
    isLoading,
    isFetching,
    isError,
    error,
    refetch
  } = source.usePreviewQuery(
    request,
    {
      skip: !enabled,
      refetchOnMountOrArgChange: false
    }
  )

  const load = useCallback((recordNumber: number, options?: LoadOptions): void => {
    const currentConfig = getCurrentConfig?.()

    setRequestedRecordIndex(recordNumber)
    setRequest({
      recordNumber,
      ...(currentConfig !== undefined && { currentConfig })
    })

    if (options?.forceRefetch === true) {
      setShouldForceRefetch(true)
    }
  }, [getCurrentConfig])

  useEffect(() => {
    if (!enabled) return
    if (request !== undefined) return
    load(0)
  }, [enabled, request, load])

  useEffect(() => {
    if (!shouldForceRefetch || request === undefined) return
    setShouldForceRefetch(false)
    void refetch()
  }, [shouldForceRefetch, request, refetch])

  // Trigger a refetch of the current record when the force-refresh token changes.
  // Skip the initial mount (token === 0) to avoid a duplicate fetch on first render.
  const isFirstForceRefresh = useRef(true)
  useEffect(() => {
    if (forceRefreshToken === undefined || forceRefreshToken === 0) return
    if (isFirstForceRefresh.current) {
      isFirstForceRefresh.current = false
      return
    }
    if (!enabled || request === undefined) return
    setShouldForceRefetch(true)
  }, [forceRefreshToken, enabled, request])

  const currentRecordIndex = data?.previewRecordIndex ?? requestedRecordIndex
  const dataPreview = useMemo(
    () => ((data?.dataPreview ?? []) as Array<Record<string, any>>),
    [data]
  )

  return {
    dataPreview,
    currentRecordIndex,
    isLoading,
    isFetching,
    isError,
    error,
    load
  }
}
