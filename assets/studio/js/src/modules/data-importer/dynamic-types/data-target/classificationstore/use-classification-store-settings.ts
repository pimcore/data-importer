/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import {
  useBundleDataImporterClassificationstoreLoadAttributesQuery,
  useBundleDataImporterClassificationstoreLoadKeyNameQuery
} from '../../../data-importer-api-slice.gen'
import { splitKeyReference } from './classification-store-variant'
import type { ClassAttribute } from '../../../types'
import { useEffect, useMemo, useRef } from 'react'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { type DynamicTypeDataTargetRenderProps } from '../common/dynamic-type-data-target-abstract'

export function useClassificationStoreSettings ({
  classId,
  settings,
  transformationResultType,
  onChange
}: DynamicTypeDataTargetRenderProps): {
    attributes: ClassAttribute[]
    options: Array<{ value: string, label: string }>
    isFetching: boolean
    isLocalized: boolean
    isKeyLocalized: boolean
    isKeyUnanswered: boolean
    variants: string[]
  } {
  const { data, isFetching } = useBundleDataImporterClassificationstoreLoadAttributesQuery(
    { classId: classId ?? '' },
    { skip: classId === undefined }
  )

  const keyId: string = settings?.keyId ?? ''
  const fieldName: string = settings?.fieldName ?? ''

  // currentData, not data: while another key is being fetched the previous key's variants must not be offered
  const { currentData: keyName, isError: isKeyError, error: keyError } = useBundleDataImporterClassificationstoreLoadKeyNameQuery(
    { keyId: splitKeyReference(keyId).reference, classId: classId ?? '', fieldName },
    { skip: keyId === '' || fieldName === '' || classId === undefined || classId === '' }
  )
  const variants: string[] = keyName?.variants ?? []
  const isKeyLocalized: boolean = keyName?.localized === true
  // A key is selected but its answer is not held yet (in flight, or not askable without a class): that is not
  // "not localized", so the panel keeps the Language control rather than let an entry for a localized key be
  // saved without one. A failed request is its own state: it has toasted, will not be retried, and the control
  // is not offered on the strength of an answer that never came.
  const isKeyUnanswered: boolean = keyId !== '' && fieldName !== '' && keyName === undefined && !isKeyError

  useEffect(() => {
    if (isKeyError && keyError !== undefined) {
      trackError(new ApiError(keyError as any))
    }
  }, [isKeyError, keyError])

  const attributes: ClassAttribute[] = useMemo(() => {
    const rawAttributes = data?.attributes ?? []
    return (
      rawAttributes as Array<{
        key?: string
        title?: string
        name?: string
        localized?: boolean
      }>
    )
      .filter((attribute) => attribute.key !== undefined)
      .map((attribute) => ({
        key: attribute.key ?? '',
        title: attribute.title ?? attribute.name ?? attribute.key ?? '',
        localized: attribute.localized ?? false
      }))
  }, [data?.attributes])

  const isLocalized = useMemo(() => attributes.find((a) => a.key === settings?.fieldName)?.localized ?? false, [attributes, settings?.fieldName])
  const options = useMemo(() => attributes.map((a) => ({ value: a.key, label: a.title })), [attributes])

  // Match ExtJS behavior: changing transformation result type invalidates previously selected classification store key.
  const prevResultType = useRef(transformationResultType)
  useEffect(() => {
    if (prevResultType.current === transformationResultType) {
      return
    }

    prevResultType.current = transformationResultType
    if (settings?.keyId !== undefined) {
      onChange({ ...settings, keyId: undefined })
    }
  }, [transformationResultType, settings?.keyId])

  return { attributes, options, isFetching, isLocalized, isKeyLocalized, isKeyUnanswered, variants }
}
