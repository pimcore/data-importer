/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect, useMemo } from 'react'
import { useResultPreviewContext } from '../result-preview/result-preview-context'
import { resolveAttrMapKey } from '../../../../../types'
import { type StepTargetProps } from './step-target'

export function useClassAttributes ({
  attributesMap,
  transformationResultType,
  dataTarget,
  onDataTargetChange
}: StepTargetProps): { classFieldOptions: Array<{ value: string, label: string }>, isLocalized: boolean } {
  const { isFetchingAttributes, calculateTypeError } = useResultPreviewContext()
  const attributes = useMemo(
    () => attributesMap[resolveAttrMapKey(transformationResultType)] ?? [],
    [attributesMap, transformationResultType]
  )
  const classFieldOptions = useMemo(
    () => ((calculateTypeError ?? '') !== '' ? [] : attributes.map((a) => ({ value: a.key, label: a.title }))),
    [calculateTypeError, attributes]
  )
  const isLocalized = useMemo(
    () => attributes.find((a) => a.key === dataTarget?.settings?.fieldName)?.localized ?? false,
    [attributes, dataTarget?.settings?.fieldName]
  )

  // Reset fieldName when it no longer exists in the loaded attributes or when type calculation failed.
  // Classification-store targets manage their own field (a classification store attribute) via
  // useClassificationStoreSettings — it is not an object class field and must not be reset based on
  // the result-type-compatible class attributes, otherwise switching the transformation result type
  // would wrongly clear the selected classification store field.
  useEffect(() => {
    const targetType = dataTarget?.type
    const isClassificationStoreTarget = targetType === 'classificationstore' || targetType === 'classificationstoreBatch'
    const currentFieldName = dataTarget?.settings?.fieldName
    if (isClassificationStoreTarget || isFetchingAttributes || currentFieldName === undefined) {
      return
    }

    if (!classFieldOptions.some((a) => a.value === currentFieldName)) {
      onDataTargetChange({
        ...dataTarget,
        settings: {
          ...dataTarget?.settings,
          fieldName: undefined,
          language: undefined
        }
      })
    }
  }, [classFieldOptions, isFetchingAttributes])

  return { classFieldOptions, isLocalized }
}
