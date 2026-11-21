/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { apiErrorMessage, isNotFoundError } from '../../../../../utils/api-error-message'
import { useMappingSource } from '../../mapping-source/mapping-source'
import { useMappingPreviewMode } from '../preview-mode/mapping-preview-mode'

export type MappingPreview =
  | { status: 'loading' }
  | { status: 'failed' }
  | { status: 'ready', value: string }

/**
 * The transformation result of the mapping at `fieldIndex` (its position in the form list) for
 * the selected record; null while preview mode is off. Every row asks with the same request,
 * so the query cache sends one request for all mappings.
 */
export const useMappingPreview = (fieldIndex: number): MappingPreview | null => {
  const { t } = useTranslation()
  const { enabled, previewRequest, previewPending, setPreviewError, markNoPreviewData } = useMappingPreviewMode()
  const source = useMappingSource()

  const { data, isFetching, isError, isSuccess, error } = source.useTransformationResultQuery(
    previewRequest ?? undefined,
    { skip: !enabled || previewRequest === null }
  )

  // a failed request fails every row; the preview line shows its message once.
  // Without preview data (404) the line shows the upload/copy hint instead
  const noPreviewData = isError && isNotFoundError(error)

  useEffect(() => {
    if (!enabled || isFetching) return
    if (noPreviewData) {
      markNoPreviewData()
    } else if (isError) {
      setPreviewError(apiErrorMessage(error) ?? t('data-importer.mapping.preview-mode.unavailable'))
    } else if (isSuccess) {
      setPreviewError(null)
    }
  }, [enabled, isFetching, noPreviewData, isError, isSuccess, error, setPreviewError, markNoPreviewData, t])

  if (!enabled) return null
  // after an edit, and while the newer request runs, the cache still holds the older response: never show it
  if (previewRequest === null || previewPending || isFetching || noPreviewData || (!isError && data === undefined)) return { status: 'loading' }
  if (isError) return { status: 'failed' }

  return { status: 'ready', value: data?.transformationResultPreviews[fieldIndex] ?? '' }
}
