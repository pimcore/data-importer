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

interface ConditionalConversionTransformerConfig {
  original?: string
  converted?: string
}

interface ConditionalConversionTransformerFormProps {
  settings: ConditionalConversionTransformerConfig
  onChange: (settings: ConditionalConversionTransformerConfig) => void
}

export const ConditionalConversionTransformerForm = ({ settings, onChange }: ConditionalConversionTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Original</span> }
          >
            <Input
              onChange={ (e) => { update('original', e.target.value) } }
              value={ settings.original ?? '' }
            />
          </Form.Item>
          <Form.Item
            className={ styles.formItemLast }
            label={ <span className={ styles.label }>Converted</span> }
          >
            <Input
              onChange={ (e) => { update('converted', e.target.value) } }
              value={ settings.converted ?? '' }
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
