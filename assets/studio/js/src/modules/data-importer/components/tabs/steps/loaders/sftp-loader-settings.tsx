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
import { Form, Input, InputNumber, InputPassword } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const SftpLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <>
      <Form.Item
        label={ t('data-importer.loader.sftp.host') }
        name={ ['loaderConfig', 'settings', 'host'] }
        required
      >
        <Input placeholder="example.com" />
      </Form.Item>

      <Form.Item
        initialValue={ 22 }
        label={ t('data-importer.loader.sftp.port') }
        name={ ['loaderConfig', 'settings', 'port'] }
        required
      >
        <InputNumber
          max={ 65535 }
          min={ 1 }
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sftp.username') }
        name={ ['loaderConfig', 'settings', 'username'] }
        required
      >
        <Input />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sftp.password') }
        name={ ['loaderConfig', 'settings', 'password'] }
        required
      >
        <InputPassword />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sftp.remote-path') }
        name={ ['loaderConfig', 'settings', 'remotePath'] }
        required
      >
        <Input placeholder="/path/to/file.csv" />
      </Form.Item>
    </>
  )
}
