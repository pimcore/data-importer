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
import { Form, FormKit, Input, Switch } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const XlsxInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <FormKit.Panel>
      <Form.Item
        name={ ['interpreterConfig', 'settings', 'skipFirstRow'] }
        valuePropName="checked"
      >
        <Switch labelRight={ t('data-importer.interpreter.xlsx.skip-first-row') } />
      </Form.Item>

      <Form.Item
        initialValue="Sheet1"
        label={ t('data-importer.interpreter.xlsx.sheet-name') }
        name={ ['interpreterConfig', 'settings', 'sheetName'] }
      >
        <Input />
      </Form.Item>
    </FormKit.Panel>
  )
}
