/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useCallback, useEffect, useMemo, useState } from 'react'
import { useBundleDataImporterConfigLoadPreviewQuery } from '../../../../data-importer-api-slice.gen'
import { type BackendConfiguration } from '../../../../utils/transformers'

interface UsePreviewRecordQueryParams {
  configName: string
  enabled: boolean
  getCurrentConfig?: () => BackendConfiguration
}

interface LoadOptions {
  forceRefetch?: boolean
}

interface PreviewRequest {
  name: string
  bundleDataImporterLoadPreviewParameters: {
    recordNumber: number
    currentConfig?: BackendConfiguration
  }
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
  configName,
  enabled,
  getCurrentConfig
}: UsePreviewRecordQueryParams): UsePreviewRecordQueryResult {
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
  } = useBundleDataImporterConfigLoadPreviewQuery(
    request as NonNullable<typeof request>,
    {
      skip: !enabled || request === undefined,
      refetchOnMountOrArgChange: false
    }
  )

  const load = useCallback((recordNumber: number, options?: LoadOptions): void => {
    const currentConfig = getCurrentConfig?.()

    setRequestedRecordIndex(recordNumber)
    setRequest({
      name: configName,
      bundleDataImporterLoadPreviewParameters: {
        recordNumber,
        ...(currentConfig !== undefined && { currentConfig })
      }
    })

    if (options?.forceRefetch === true) {
      setShouldForceRefetch(true)
    }
  }, [configName, getCurrentConfig])

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
