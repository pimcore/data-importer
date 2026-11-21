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

interface LoadAssetTransformerConfig {
  loadStrategy?: string
}

interface LoadAssetTransformerFormProps {
  settings: LoadAssetTransformerConfig
  onChange: (settings: LoadAssetTransformerConfig) => void
}

export const LoadAssetTransformerForm = ({ settings, onChange }: LoadAssetTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <Form.Item
          className={ styles.formItemLast }
          label={ <span className={ styles.label }>Load strategy</span> }
        >
          <Select
            onChange={ (v) => { update('loadStrategy', v) } }
            options={ [
              { value: 'path', label: 'By path' },
              { value: 'id', label: 'By ID' }
            ] }
            value={ settings.loadStrategy ?? 'path' }
          />
        </Form.Item>
      ) }
    </TransformerSettingsLayout>
  )
}
