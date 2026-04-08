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
import { type MappingConfigItem, type ClassAttribute, resolveAttrMapKey, DEFAULT_ATTR_MAP_KEY } from '../../../../../types'
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../../data-importer-api-slice.gen'
import { parseClassAttribute } from '../../mapping-step/hooks/use-mapping-step-loader.types'

interface UseAutoRecalculateTypeArgs {
  open: boolean
  configName: string
  classId?: string
  localItem: MappingConfigItem
  localItemRef: React.RefObject<MappingConfigItem>
  attributesMap: Record<string, ClassAttribute[]>
  setCalculateTypeRequest: (request: {
    name: string
    bundleDataImporterCalculateTransformationResultTypeParameters: {
      currentConfig: {
        label?: string
        dataSourceIndex?: string[]
        transformationPipeline?: object[]
        dataTarget?: object
      }
    }
  }) => void
}

interface UseAutoRecalculateTypeResult {
  mergedAttributesMap: Record<string, ClassAttribute[]>
  isFetchingExtraAttributes: boolean
}

export function useAutoRecalculateType ({
  open,
  configName,
  classId,
  localItem,
  localItemRef,
  attributesMap,
  setCalculateTypeRequest
}: UseAutoRecalculateTypeArgs): UseAutoRecalculateTypeResult {
  const pipelineKey = JSON.stringify(localItem.transformationPipeline ?? [])
  const dataSourceKey = JSON.stringify(localItem.dataSourceIndex ?? [])
  const isInitialOpenRef = useRef(true)

  useEffect(() => {
    if (!open) {
      isInitialOpenRef.current = true
      return
    }
    if (isInitialOpenRef.current) {
      isInitialOpenRef.current = false
      return
    }

    const current = localItemRef.current ?? {}
    setCalculateTypeRequest({
      name: configName,
      bundleDataImporterCalculateTransformationResultTypeParameters: {
        currentConfig: {
          label: current.label,
          dataSourceIndex: current.dataSourceIndex,
          transformationPipeline: current.transformationPipeline as object[] | undefined,
          dataTarget: current.dataTarget as object | undefined
        }
      }
    })
  }, [pipelineKey, dataSourceKey, open, configName, localItemRef, setCalculateTypeRequest])

  const currentAttrMapKey = resolveAttrMapKey(localItem.transformationResultType)
  const needsAttrFetch = currentAttrMapKey !== DEFAULT_ATTR_MAP_KEY && attributesMap[currentAttrMapKey] === undefined
  const { data: extraAttrData, isFetching: isFetchingExtraAttributes } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    {
      classId: classId ?? '',
      transformationResultType: localItem.transformationResultType,
      systemWrite: true
    },
    { skip: !needsAttrFetch || classId === undefined || classId === '' }
  )

  const mergedAttributesMap: Record<string, ClassAttribute[]> = useMemo(() => {
    if (!needsAttrFetch || extraAttrData?.attributes === undefined) return attributesMap
    const parsed: ClassAttribute[] = extraAttrData.attributes.map(parseClassAttribute)
    return { ...attributesMap, [currentAttrMapKey]: parsed }
  }, [attributesMap, needsAttrFetch, extraAttrData, currentAttrMapKey])

  return { mergedAttributesMap, isFetchingExtraAttributes: needsAttrFetch && isFetchingExtraAttributes }
}
