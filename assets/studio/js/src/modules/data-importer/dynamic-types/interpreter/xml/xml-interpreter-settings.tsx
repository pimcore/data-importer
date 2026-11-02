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
import { Form, FormKit, Input, TextArea } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useStyles } from './xml-interpreter-settings.styles'

export const XmlInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <FormKit.Panel>
      <Form.Item
        initialValue="/root/item"
        label={ t('data-importer.interpreter.xml.xpath') }
        name={ ['interpreterConfig', 'settings', 'xpath'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.interpreter.xml.xpath') }) }
        ] }
      >
        <Input />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.interpreter.xml.schema') }
        name={ ['interpreterConfig', 'settings', 'schema'] }
      >
        <TextArea
          className={ styles.monoTextArea }
          rows={ 6 }
        />
      </Form.Item>
    </FormKit.Panel>
  )
}
