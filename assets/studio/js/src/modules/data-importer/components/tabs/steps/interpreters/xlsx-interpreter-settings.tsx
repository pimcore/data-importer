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
import { Form, Input, Checkbox, InputNumber } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const XlsxInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <>
      <Form.Item
        label={ t('data-importer.interpreter.xlsx.skip-first-row') }
        name={ ['interpreterConfig', 'settings', 'skipFirstRow'] }
        valuePropName="checked"
      >
        <Checkbox />
      </Form.Item>

      <Form.Item
        initialValue="Sheet1"
        label={ t('data-importer.interpreter.xlsx.sheet-name') }
        name={ ['interpreterConfig', 'settings', 'sheetName'] }
      >
        <Input />
      </Form.Item>

      <Form.Item
        initialValue={ 0 }
        label={ t('data-importer.interpreter.xlsx.skip-rows') }
        name={ ['interpreterConfig', 'settings', 'skipRows'] }
      >
        <InputNumber min={ 0 } />
      </Form.Item>
    </>
  )
}
