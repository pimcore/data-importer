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
import { Switch, Form } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface NumericTransformerConfig {
  returnNullIfEmpty?: boolean
}

interface NumericTransformerFormProps {
  settings: NumericTransformerConfig
  onChange: (settings: NumericTransformerConfig) => void
}

export const NumericTransformerForm = ({ settings, onChange }: NumericTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <Form.Item className={ styles.formItemLast }>
          <Switch
            checked={ settings.returnNullIfEmpty ?? false }
            labelRight="Return null if empty"
            onChange={ (v) => { update('returnNullIfEmpty', v) } }
            size="small"
          />
        </Form.Item>
      ) }
    </TransformerSettingsLayout>
  )
}
