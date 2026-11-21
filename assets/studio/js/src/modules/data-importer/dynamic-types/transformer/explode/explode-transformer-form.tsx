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
import { Input, Switch, Form } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface ExplodeTransformerConfig {
  delimiter?: string
  keepSubArrays?: boolean
}

interface ExplodeTransformerFormProps {
  settings: ExplodeTransformerConfig
  onChange: (settings: ExplodeTransformerConfig) => void
}

export const ExplodeTransformerForm = ({ settings, onChange }: ExplodeTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Delimiter</span> }
          >
            <Input
              onChange={ (e) => { update('delimiter', e.target.value) } }
              value={ settings.delimiter ?? '' }
            />
          </Form.Item>
          <Form.Item className={ styles.formItemLast }>
            <Switch
              checked={ settings.keepSubArrays ?? false }
              labelRight="Keep sub-arrays"
              onChange={ (v) => { update('keepSubArrays', v) } }
              size="small"
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
