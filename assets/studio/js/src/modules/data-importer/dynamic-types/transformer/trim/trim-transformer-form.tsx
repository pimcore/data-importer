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
import { Select, Form } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface TrimTransformerConfig {
  mode?: string
}

interface TrimTransformerFormProps {
  settings: TrimTransformerConfig
  onChange: (settings: TrimTransformerConfig) => void
}

export const TrimTransformerForm = ({ settings, onChange }: TrimTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <Form.Item
          className={ styles.formItemLast }
          label={ <span className={ styles.label }>Mode</span> }
        >
          <Select
            onChange={ (v) => { update('mode', v) } }
            options={ [
              { value: 'both', label: 'Both' },
              { value: 'left', label: 'Left' },
              { value: 'right', label: 'Right' }
            ] }
            value={ settings.mode ?? 'both' }
          />
        </Form.Item>
      ) }
    </TransformerSettingsLayout>
  )
}
