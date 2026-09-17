/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect, useMemo, useRef } from 'react'
import { Form } from '@pimcore/studio-ui-bundle/components'
import { useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import { useBundleDataImporterConfigLoadColumnHeadersQuery } from '../data-importer-api-slice.gen'
import { transformFormToBackend, type BackendConfiguration } from '../utils/transformers'
import { type DataImporterFormValues } from '../types'

export interface ColumnHeaderOption {
  value: string
  label: string
}

/**
 * Returns the preview "Data Source Index" column options, loaded live from the current
 * loader/interpreter config (persisted `configData.columnHeaders` can be stale).
 *
 * Bump `previewVersion` when the preview data changes to trigger a refetch.
 * Must be called from within the detail-view Form context.
 */
export const useColumnHeaderOptions = (
  configName: string,
  active: boolean,
  previewVersion: number = 0
): ColumnHeaderOption[] => {
  const form = Form.useFormInstance()
  const { data: configData } = useBundleDataImporterConfigGetQuery({ name: configName })

  const loaderConfigType = Form.useWatch(['loaderConfig', 'type']) as string | undefined
  const interpreterConfigType = Form.useWatch(['interpreterConfig', 'type']) as string | undefined

  const headersRequest = useMemo(() => {
    if (configData === undefined || loaderConfigType === undefined) return undefined
    const formValues = form.getFieldsValue(true) as DataImporterFormValues
    const existingConfig = (configData.configuration ?? {}) as BackendConfiguration
    const backendConfig = transformFormToBackend(formValues, existingConfig)
    return {
      name: configName,
      bundleDataImporterCopyPreviewParameters: {
        currentConfig: {
          loaderConfig: backendConfig.loaderConfig,
          interpreterConfig: backendConfig.interpreterConfig
        }
      }
    }
  }, [configName, configData, form, loaderConfigType, interpreterConfigType])

  const { data: liveHeaders, refetch } = useBundleDataImporterConfigLoadColumnHeadersQuery(
    headersRequest!,
    { skip: headersRequest === undefined || !active }
  )

  // Refetch on preview-data change (the query key doesn't change after a copy/upload).
  const lastFetchedVersionRef = useRef(previewVersion)
  useEffect(() => {
    if (!active || headersRequest === undefined) return
    if (previewVersion !== lastFetchedVersionRef.current) {
      lastFetchedVersionRef.current = previewVersion
      void refetch().catch(() => undefined)
    }
  }, [active, previewVersion, headersRequest, refetch])

  return useMemo(
    () => (liveHeaders?.columnHeaders ?? configData?.columnHeaders ?? []).map((header) => {
      // API returns {dataIndex, label} objects; the persisted type says string[] — handle both
      const h = header as unknown as { dataIndex?: string, label?: string } | string
      const value = typeof h === 'string' ? h : (h.dataIndex ?? '')
      const label = typeof h === 'string' ? h : (h.label ?? h.dataIndex ?? '')
      return { value, label }
    }),
    [liveHeaders?.columnHeaders, configData?.columnHeaders]
  )
}
