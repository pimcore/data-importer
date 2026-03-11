/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useState } from 'react'
import { Alert, Button, Form, Input, Space, ImportModal, Spin } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { getPrefix } from '@pimcore/studio-ui-bundle/api'
import { useBundleDataImporterConfigHasImportFileUploadedQuery } from '../../../../data-importer-api-slice-enhanced'

export interface UploadLoaderSettingsProps {
  configName: string
}

export const UploadLoaderSettings = ({ configName }: UploadLoaderSettingsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const form = Form.useFormInstance()
  const [modalOpen, setModalOpen] = useState(false)
  const {
    data: fileStatus,
    isFetching,
    isLoading,
    isError,
    refetch
  } = useBundleDataImporterConfigHasImportFileUploadedQuery({ name: configName })

  const uploadAction = `${getPrefix()}/bundle/data-importer/config/${configName}/upload-import-file`

  useEffect(() => {
    const nextPath = (fileStatus?.filePath as string | undefined) ?? ''
    const currentPath = (form.getFieldValue(['loaderConfig', 'settings', 'uploadFilePath']) as string | undefined) ?? ''

    if (currentPath !== nextPath) {
      // Keep the hidden field in sync for save payload, but do not mark the form
      // as user-edited when this value comes from server-side status polling.
      form.setFieldValue(
        ['loaderConfig', 'settings', 'uploadFilePath'],
        nextPath,
        { triggerChange: false }
      )
    }
  }, [fileStatus?.filePath, form])

  const hasUploadedFile = fileStatus?.exists === true
  const isCheckingStatus = isLoading || (isFetching && fileStatus === undefined)

  const statusType: 'success' | 'warning' | 'error' = isError
    ? 'error'
    : (hasUploadedFile ? 'success' : 'warning')
  const statusMessage = fileStatus?.message ?? (hasUploadedFile
    ? t('data-importer.loader.upload.file-uploaded')
    : t('data-importer.loader.upload.no-file'))

  return (
    <Space
      direction="vertical"
      size="small"
    >
      { isCheckingStatus
        ? <Spin type="classic" />
        : (
          <Alert
            message={ statusMessage }
            type={ statusType }
          />
          ) }

      <Form.Item
        hidden
        name={ ['loaderConfig', 'settings', 'uploadFilePath'] }
      >
        <Input />
      </Form.Item>

      <Button
        onClick={ () => { setModalOpen(true) } }
        type="primary"
      >
        { t('data-importer.loader.upload.open-upload') }
      </Button>

      <ImportModal
        action={ uploadAction }
        onOpenChange={ setModalOpen }
        onUploadSuccess={ () => { void refetch() } }
        open={ modalOpen }
        title={ t('data-importer.loader.upload.modal-title') }
      />
    </Space>
  )
}
