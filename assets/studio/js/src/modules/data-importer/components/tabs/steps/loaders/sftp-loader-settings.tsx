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
import { Form, FormKit, Input, InputNumber, InputPassword } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const SftpLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <FormKit.Panel>
      <Form.Item
        label={ t('data-importer.loader.sftp.host') }
        name={ ['loaderConfig', 'settings', 'host'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.sftp.host') }) }
        ] }
      >
        <Input placeholder="example.com" />
      </Form.Item>

      <Form.Item
        initialValue={ 22 }
        label={ t('data-importer.loader.sftp.port') }
        name={ ['loaderConfig', 'settings', 'port'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.sftp.port') }) }
        ] }
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
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.sftp.username') }) }
        ] }
      >
        <Input />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sftp.password') }
        name={ ['loaderConfig', 'settings', 'password'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.sftp.password') }) }
        ] }
      >
        <InputPassword />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sftp.remote-path') }
        name={ ['loaderConfig', 'settings', 'remotePath'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.sftp.remote-path') }) }
        ] }
      >
        <Input placeholder="/path/to/file.csv" />
      </Form.Item>
    </FormKit.Panel>
  )
}
