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
import { Form, Input, TextArea } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const XmlInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <>
      <Form.Item
        initialValue="/root/item"
        label={ t('data-importer.interpreter.xml.xpath') }
        name={ ['interpreterConfig', 'settings', 'xpath'] }
        required
      >
        <Input />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.interpreter.xml.schema') }
        name={ ['interpreterConfig', 'settings', 'schema'] }
      >
        <TextArea
          rows={ 6 }
          style={ { fontFamily: 'monospace' } }
        />
      </Form.Item>
    </>
  )
}
