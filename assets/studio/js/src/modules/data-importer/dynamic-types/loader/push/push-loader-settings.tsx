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
import { Input, Button, Compact, Alert, Form, Icon, Switch, FormKit } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { v4 as uuidv4 } from 'uuid'
import { useStyles } from './push-loader-settings.styles'

export const PushLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const form = Form.useFormInstance()
  const configName = Form.useWatch(['name']) as string | undefined

  const generateApiKey = (): void => {
    const apiKey = uuidv4()
    form.setFieldValue(['loaderConfig', 'settings', 'apiKey'], apiKey, { triggerChange: true })
  }

  const pushEndpoint = `${window.location.protocol}//${window.location.host}/pimcore-datahub-import/${configName ?? ''}/push`

  return (
    <FormKit.Panel>
      <Form.Item
        label={ t('data-importer.loader.push.api-key') }
        required
      >
        <Compact className={ styles.fullWidth }>
          <Form.Item
            name={ ['loaderConfig', 'settings', 'apiKey'] }
            noStyle
            rules={ [
              { required: true, message: t('data-importer.loader.push.api-key-required') },
              { min: 16, message: t('data-importer.loader.push.api-key-min-length') }
            ] }
          >
            <Input />
          </Form.Item>
          <Button
            htmlType="button"
            icon={ <Icon value="reload" /> }
            onClick={ generateApiKey }
            type="default"
          >
            {t('data-importer.loader.push.api-key.generate')}
          </Button>
        </Compact>
      </Form.Item>

      <Form.Item
        name={ ['loaderConfig', 'settings', 'ignoreNotEmptyQueue'] }
        valuePropName="checked"
      >
        <Switch labelRight={ t('data-importer.loader.push.ignore-not-empty-queue') } />
      </Form.Item>

      <Form.Item label={ t('data-importer.loader.push.endpoint') }>
        <Alert
          message={ pushEndpoint }
          type="info"
        />
      </Form.Item>
    </FormKit.Panel>
  )
}
