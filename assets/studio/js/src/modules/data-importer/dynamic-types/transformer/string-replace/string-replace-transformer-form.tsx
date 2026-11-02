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

interface StringReplaceTransformerConfig {
  search?: string
  replace?: string
}

interface StringReplaceTransformerFormProps {
  settings: StringReplaceTransformerConfig
  onChange: (settings: StringReplaceTransformerConfig) => void
}

export const StringReplaceTransformerForm = ({ settings, onChange }: StringReplaceTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Search</span> }
          >
            <Input
              onChange={ (e) => { update('search', e.target.value) } }
              value={ settings.search ?? '' }
            />
          </Form.Item>
          <Form.Item
            className={ styles.formItemLast }
            label={ <span className={ styles.label }>Replace</span> }
          >
            <Input
              onChange={ (e) => { update('replace', e.target.value) } }
              value={ settings.replace ?? '' }
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
