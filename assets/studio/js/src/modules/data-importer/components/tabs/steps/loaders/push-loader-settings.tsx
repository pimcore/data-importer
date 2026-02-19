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
import { Input, Checkbox, Button, Compact, Alert, Form, Icon } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { v4 as uuidv4 } from 'uuid'

export const PushLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()
  const form = Form.useFormInstance()

  const generateApiKey = (): void => {
    const apiKey = uuidv4()
    form.setFieldValue(['loaderConfig', 'settings', 'apiKey'], apiKey)
  }

  const pushEndpoint = `${window.location.protocol}//${window.location.host}/pimcore-studio/api/bundle/data-importer/push`

  return (
    <>
      <Form.Item
        label={ t('data-importer.loader.push.api-key') }
        name={ ['loaderConfig', 'settings', 'apiKey'] }
        required
        rules={ [
          { min: 16, message: t('data-importer.loader.push.api-key-min-length') }
        ] }
      >
        <Compact style={ { width: '100%' } }>
          <Input />
          <Button
            icon={ <Icon value="reload" /> }
            onClick={ generateApiKey }
            type="default"
          >
            {t('data-importer.loader.push.api-key.generate')}
          </Button>
        </Compact>
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.push.ignore-not-empty-queue') }
        name={ ['loaderConfig', 'settings', 'ignoreNotEmptyQueue'] }
        valuePropName="checked"
      >
        <Checkbox />
      </Form.Item>

      <Form.Item label={ t('data-importer.loader.push.endpoint') }>
        <Alert
          message={ pushEndpoint }
          type="info"
        />
      </Form.Item>
    </>
  )
}
