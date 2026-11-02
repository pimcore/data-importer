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
import { Input, Form } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface DateTransformerConfig {
  format?: string
}

interface DateTransformerFormProps {
  settings: DateTransformerConfig
  onChange: (settings: DateTransformerConfig) => void
}

export const DateTransformerForm = ({ settings, onChange }: DateTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <Form.Item
          className={ styles.formItemLast }
          label={ <span className={ styles.label }>Format</span> }
        >
          <Input
            onChange={ (e) => { update('format', e.target.value) } }
            placeholder="Y-m-d"
            value={ settings.format ?? 'Y-m-d' }
          />
        </Form.Item>
      ) }
    </TransformerSettingsLayout>
  )
}
