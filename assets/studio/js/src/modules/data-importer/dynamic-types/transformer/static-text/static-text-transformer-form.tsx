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
import { Select, Input, Switch, Form } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface StaticTextTransformerConfig {
  mode?: string
  text?: string
  alwaysAdd?: boolean
}

interface StaticTextTransformerFormProps {
  settings: StaticTextTransformerConfig
  onChange: (settings: StaticTextTransformerConfig) => void
}

export const StaticTextTransformerForm = ({ settings, onChange }: StaticTextTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Mode</span> }
          >
            <Select
              onChange={ (v) => { update('mode', v) } }
              options={ [
                { value: 'append', label: 'Append' },
                { value: 'prepend', label: 'Prepend' }
              ] }
              value={ settings.mode ?? 'append' }
            />
          </Form.Item>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Text</span> }
          >
            <Input
              onChange={ (e) => { update('text', e.target.value) } }
              value={ settings.text ?? '' }
            />
          </Form.Item>
          <Form.Item className={ styles.formItemLast }>
            <Switch
              checked={ settings.alwaysAdd ?? false }
              labelRight="Always add"
              onChange={ (v) => { update('alwaysAdd', v) } }
              size="small"
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
