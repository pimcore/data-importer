/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useCallback, useEffect, useState } from 'react'
import { Form } from '@pimcore/studio-ui-bundle/components'
import { useAppDispatch } from '@pimcore/studio-ui-bundle/app'
import { api, useBundleDataImporterConfigGetQuery } from '../../../../../data-importer-api-slice-enhanced'
import { useBundleDataImporterConfigLoadColumnHeadersQuery, useBundleDataImporterConfigLoadPreviewQuery } from '../../../../../data-importer-api-slice.gen'
import { transformFormToBackend, type BackendConfiguration } from '../../../../../utils/transformers'
import { normalizeDataRow } from '../../../../../utils/normalize-data-row'
import { type DataImporterFormValues, type MappingConfigItem, type ClassAttribute, resolveAttrMapKey } from '../../../../../types'
import { type SourceRow } from '../sources-panel/sources-panel'
import { parseClassAttribute, type ColumnHeaderEntry, type UseMappingStepLoaderResult } from './use-mapping-step-loader.types'
export function useMappingStepLoader (configName: string, isActive: boolean): UseMappingStepLoaderResult {
  const form = Form.useFormInstance()
  const dispatch = useAppDispatch()
  const { data: configData, isSuccess: isConfigLoaded, requestId } = useBundleDataImporterConfigGetQuery({ name: configName })

  const [columnHeaderOptions, setColumnHeaderOptions] = useState<Array<{ value: string, label: string }>>([])
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [sourceRows, setSourceRows] = useState<SourceRow[]>([])
  const [hasPreviewError, setHasPreviewError] = useState(false)
  const [attributesMap, setAttributesMap] = useState<Record<string, ClassAttribute[]>>({})
  const [headersRequest, setHeadersRequest] = useState<{
    name: string
    bundleDataImporterCopyPreviewParameters: {
      currentConfig: BackendConfiguration
    }
  } | undefined>(undefined)
  const [previewRequest, setPreviewRequest] = useState<{
    name: string
    bundleDataImporterLoadPreviewParameters: {
      currentConfig: BackendConfiguration
      recordNumber: number
    }
  } | undefined>(undefined)
  const [attrsDone, setAttrsDone] = useState(false)

  const {
    data: headersResult,
    refetch: refetchHeaders,
    isFetching: isHeadersFetching,
    isError: isHeadersError,
    isSuccess: isHeadersSuccess
  } = useBundleDataImporterConfigLoadColumnHeadersQuery(
    headersRequest!,
    {
      skip: headersRequest === undefined,
      refetchOnMountOrArgChange: false
    }
  )

  const {
    data: previewResult,
    refetch: refetchPreview,
    isFetching: isPreviewFetching,
    isError: isPreviewError,
    isSuccess: isPreviewSuccess
  } = useBundleDataImporterConfigLoadPreviewQuery(
    previewRequest!,
    {
      skip: previewRequest === undefined,
      refetchOnMountOrArgChange: false
    }
  )

  const classIdFromConfig = (configData?.configuration as BackendConfiguration | undefined)?.resolverConfig?.dataObjectClassId
  const classIdFromForm = Form.useWatch(['resolverConfig', 'dataObjectClassId']) as string | undefined
  const classId = classIdFromForm ?? classIdFromConfig

  const mappingTrtList = Form.useWatch(
    (values: { mappingConfig?: MappingConfigItem[] }) =>
      (values.mappingConfig ?? []).map((item) => item.transformationResultType ?? '')
  ) as string[] | undefined

  const getMappingConfig = useCallback(
    (): MappingConfigItem[] =>
      ((form.getFieldsValue() as DataImporterFormValues).mappingConfig ?? []),
    [form]
  )

  const getBackendConfig = useCallback((): BackendConfiguration => {
    const formValues = form.getFieldsValue(true) as DataImporterFormValues
    const existingConfig = (configData?.configuration ?? {}) as BackendConfiguration
    return transformFormToBackend(formValues, existingConfig)
  }, [form, configData])

  const getSourcePreviewConfig = useCallback((): BackendConfiguration => {
    const backendConfig = getBackendConfig()
    const sourcePreviewConfig: BackendConfiguration = {
      loaderConfig: backendConfig.loaderConfig,
      interpreterConfig: backendConfig.interpreterConfig
    }
    return sourcePreviewConfig
  }, [getBackendConfig])

  useEffect(() => {
    if (!isHeadersSuccess || headersResult === undefined) return
    const columnHeaders = headersResult.columnHeaders ?? []
    const headers = columnHeaders.map((h: ColumnHeaderEntry) => ({
      value: String(h.dataIndex ?? h.id ?? ''),
      label: String(h.label ?? h.dataIndex ?? h.id ?? '')
    }))
    setColumnHeaderOptions(headers)
  }, [isHeadersSuccess, headersResult])

  useEffect(() => {
    if (!isHeadersError) return
    setColumnHeaderOptions([])
  }, [isHeadersError])

  useEffect(() => {
    if (!isPreviewSuccess || previewResult === undefined) return

    const dataPreview = (previewResult).dataPreview ?? []
    const rows = dataPreview.map((row: Record<string, any>) => normalizeDataRow(row))
    setSourceRows(rows)
    setHasPreviewError(rows.length === 0)
  }, [isPreviewSuccess, previewResult])

  useEffect(() => {
    if (!isPreviewError) return
    setSourceRows([])
    setHasPreviewError(true)
  }, [isPreviewError])

  useEffect(() => {
    const headersDone = headersRequest !== undefined && !isHeadersFetching
    const previewDone = previewRequest !== undefined && !isPreviewFetching
    setInitialLoadDone(headersDone && attrsDone && previewDone)
  }, [headersRequest, previewRequest, isHeadersFetching, isPreviewFetching, attrsDone])

  const loaderConfigType = Form.useWatch(['loaderConfig', 'type']) as string | undefined
  const interpreterConfigType = Form.useWatch(['interpreterConfig', 'type']) as string | undefined
  const currentConfigFingerprint = JSON.stringify(getSourcePreviewConfig())
  const [lastLoadedFingerprint, setLastLoadedFingerprint] = useState<string>('')
  const [lastLoadedRequestId, setLastLoadedRequestId] = useState<string | undefined>(undefined)

  useEffect(() => {
    if (!isConfigLoaded || !isActive) return

    const nextFingerprint = currentConfigFingerprint
    const argsChanged = nextFingerprint !== lastLoadedFingerprint
    const requestChanged = requestId !== undefined && requestId !== lastLoadedRequestId

    if (!argsChanged && !requestChanged) return

    setInitialLoadDone(false)
    setAttrsDone(false)
    setHasPreviewError(false)

    setHeadersRequest({
      name: configName,
      bundleDataImporterCopyPreviewParameters: {
        currentConfig: getSourcePreviewConfig()
      }
    })

    setPreviewRequest({
      name: configName,
      bundleDataImporterLoadPreviewParameters: {
        currentConfig: getSourcePreviewConfig(),
        recordNumber: 0
      }
    })

    if (requestChanged && !argsChanged && headersRequest !== undefined && previewRequest !== undefined) {
      void Promise.all([
        refetchHeaders().catch(() => undefined),
        refetchPreview().catch(() => undefined)
      ])
    }

    void (async () => {
      try {
        const classIdFromFormSync = form.getFieldValue(['resolverConfig', 'dataObjectClassId']) as string | undefined
        const effectiveClassId = classIdFromFormSync ?? classIdFromConfig
        const backendConfig = (configData?.configuration ?? {}) as BackendConfiguration
        const items: MappingConfigItem[] = (backendConfig.mappingConfig) ?? []

        const uniqueTypes = new Set<string | undefined>()
        if (effectiveClassId !== undefined && effectiveClassId !== '') {
          uniqueTypes.add(undefined)
          items.forEach((item) => { uniqueTypes.add(item.transformationResultType) })
        }

        const typesArray = Array.from(uniqueTypes)
        const attrPromises = typesArray.map(async (trt) =>
          await dispatch(
            api.endpoints.bundleDataImporterDataTypeLoadClassAttributes.initiate({
              classId: effectiveClassId!,
              transformationResultType: trt,
              systemWrite: true
            }, {
              forceRefetch: requestChanged
            })
          )
        )

        const attrResults = await Promise.all(attrPromises)

        if (typesArray.length > 0) {
          const newMap: Record<string, ClassAttribute[]> = {}
          attrResults.forEach((result, i) => {
            const trt = typesArray[i]
            const mapKey = (trt === undefined || trt === '' || trt === 'default') ? '__default__' : trt
            const attrs = (result.data?.attributes ?? []).map(parseClassAttribute)
            newMap[mapKey] = attrs
          })
          setAttributesMap(newMap)
        }
      } finally {
        setAttrsDone(true)
      }
    })()

    setLastLoadedFingerprint(nextFingerprint)
    setLastLoadedRequestId(requestId)
  }, [
    isConfigLoaded,
    isActive,
    configName,
    currentConfigFingerprint,
    lastLoadedFingerprint,
    requestId,
    lastLoadedRequestId,
    getSourcePreviewConfig,
    headersRequest,
    previewRequest,
    refetchHeaders,
    refetchPreview,
    dispatch,
    form,
    configData,
    classIdFromConfig,
    loaderConfigType,
    interpreterConfigType
  ])

  useEffect(() => {
    const effectiveClassId = classId
    if (effectiveClassId === undefined || effectiveClassId === '' || !initialLoadDone) return

    const items = getMappingConfig()
    const missingTypes = new Set<string>()
    items.forEach((item) => {
      const trt = item.transformationResultType
      const mapKey = resolveAttrMapKey(trt)
      if (attributesMap[mapKey] === undefined) {
        missingTypes.add(mapKey)
      }
    })

    if (missingTypes.size === 0) return

    const missingArray = Array.from(missingTypes)
    const promises = missingArray.map(async (mapKey) => {
      const trt = mapKey === '__default__' ? undefined : mapKey
      return await dispatch(
        api.endpoints.bundleDataImporterDataTypeLoadClassAttributes.initiate({
          classId: effectiveClassId,
          transformationResultType: trt,
          systemWrite: true
        })
      )
    })

    void Promise.all(promises).then((results) => {
      setAttributesMap((prev) => {
        const next = { ...prev }
        results.forEach((result, i) => {
          next[missingArray[i]] = (result.data?.attributes ?? []).map(parseClassAttribute)
        })
        return next
      })
    })
  }, [mappingTrtList, initialLoadDone, classId])

  return {
    columnHeaderOptions,
    initialLoadDone,
    sourceRows,
    hasPreviewError,
    attributesMap,
    setAttributesMap,
    classId,
    mappingTrtList,
    getMappingConfig
  }
}
