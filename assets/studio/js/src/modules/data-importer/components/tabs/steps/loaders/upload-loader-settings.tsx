/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { Alert, Space, Form } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useBundleDataImporterConfigHasImportFileUploadedQuery } from '../../../../data-importer-api-slice-enhanced'

export interface UploadLoaderSettingsProps {
  configName: string
}

export const UploadLoaderSettings = ({ configName }: UploadLoaderSettingsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { data: fileStatus } = useBundleDataImporterConfigHasImportFileUploadedQuery({ name: configName })

  return (
    <Space
      direction="vertical"
      size="small"
    >
      {fileStatus?.exists === true && (
        <Alert
          message={ t('data-importer.loader.upload.file-uploaded') }
          type="success"
        />
      )}
      {fileStatus?.exists === false && (
        <Alert
          message={ t('data-importer.loader.upload.no-file') }
          type="warning"
        />
      )}
      <Form.Item>
        {t('data-importer.loader.upload.description')}
      </Form.Item>
    </Space>
  )
}
