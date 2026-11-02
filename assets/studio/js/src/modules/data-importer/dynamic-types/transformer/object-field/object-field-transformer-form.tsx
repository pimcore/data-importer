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

interface ObjectFieldTransformerConfig {
  attribute?: string
  forward_parameter?: string
}

interface ObjectFieldTransformerFormProps {
  settings: ObjectFieldTransformerConfig
  onChange: (settings: ObjectFieldTransformerConfig) => void
}

export const ObjectFieldTransformerForm = ({ settings, onChange }: ObjectFieldTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Attribute</span> }
          >
            <Input
              onChange={ (e) => { update('attribute', e.target.value) } }
              value={ settings.attribute ?? '' }
            />
          </Form.Item>
          <Form.Item
            className={ styles.formItemLast }
            label={ <span className={ styles.label }>Forward parameter</span> }
          >
            <Input
              onChange={ (e) => { update('forward_parameter', e.target.value) } }
              value={ settings.forward_parameter ?? '' }
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
