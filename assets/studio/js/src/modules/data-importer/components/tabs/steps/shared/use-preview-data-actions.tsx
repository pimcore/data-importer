/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useState } from 'react'
import { ImportModal } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { getPrefix } from '@pimcore/studio-ui-bundle/api'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterConfigCopyPreviewMutation } from '../../../../data-importer-api-slice.gen'
import { withApiMessage } from '../../../../utils/api-error-message'
import { type BackendConfiguration } from '../../../../utils/transformers'
import { usePreviewContext, usePreviewScope } from '../../../preview-scope'

interface UsePreviewDataActionsParams {
  // the stored configuration the preview file belongs to; without one there is nothing to upload or copy
  configName: string | undefined
  getCurrentConfig: () => BackendConfiguration
  /** Called once the new preview data is stored. */
  onPreviewDataChange: () => void
}

export interface PreviewDataActions {
  upload: () => void
  copyFromSource: () => Promise<void>
  isCopying: boolean
  // the upload dialog; render it once beside the actions
  uploadModal: React.ReactNode
}

/** "Upload file" and "Copy from data source": the two ways to provide a configuration's preview data */
export function usePreviewDataActions ({ configName, getCurrentConfig, onPreviewDataChange }: UsePreviewDataActionsParams): PreviewDataActions {
  const { t } = useTranslation()
  const [uploadModalOpen, setUploadModalOpen] = useState(false)
  const [copyPreview, { isLoading: isCopying, error: copyPreviewError }] = useBundleDataImporterConfigCopyPreviewMutation()
  const previewScope = usePreviewScope()
  const previewContext = usePreviewContext()

  useEffect(() => {
    if (copyPreviewError === undefined) return
    trackError(new ApiError(withApiMessage(copyPreviewError)))
  }, [copyPreviewError])

  const upload = useCallback((): void => {
    setUploadModalOpen(true)
  }, [])

  const copyFromSource = useCallback(async (): Promise<void> => {
    if (configName === undefined) return

    const result = await copyPreview({
      name: configName,
      bundleDataImporterCopyPreviewParameters: {
        currentConfig: getCurrentConfig(),
        previewScope,
        previewContext
      }
    })

    if ('error' in result) {
      return
    }

    onPreviewDataChange()
  }, [configName, copyPreview, getCurrentConfig, previewScope, previewContext, onPreviewDataChange])

  const uploadModal = configName === undefined
    ? null
    : (
      <ImportModal
        action={ `${getPrefix()}/bundle/data-importer/config/${configName}/upload-preview` }
        data={ {
          ...(previewScope !== undefined && { previewScope }),
          ...(previewContext !== undefined && { previewContext })
        } }
        onOpenChange={ (nextOpen) => { setUploadModalOpen(nextOpen) } }
        onUploadSuccess={ () => {
          setUploadModalOpen(false)
          onPreviewDataChange()
        } }
        open={ uploadModalOpen }
        title={ t('data-importer.preview-import.upload-file') }
      />
      )

  return { upload, copyFromSource, isCopying, uploadModal }
}
