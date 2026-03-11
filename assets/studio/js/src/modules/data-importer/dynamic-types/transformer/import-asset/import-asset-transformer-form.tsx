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
import { Input, Form, Switch } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'

interface ImportAssetTransformerConfig {
  parentFolder?: string
  useExisting?: boolean
  overwriteExisting?: boolean
  pregMatch?: string
}

interface ImportAssetTransformerFormProps {
  settings: ImportAssetTransformerConfig
  onChange: (settings: ImportAssetTransformerConfig) => void
}

export const ImportAssetTransformerForm = ({ settings, onChange }: ImportAssetTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Parent folder</span> }
          >
            <Input
              onChange={ (e) => { update('parentFolder', e.target.value) } }
              placeholder="/"
              value={ settings.parentFolder ?? '/' }
            />
          </Form.Item>

          <Form.Item className={ styles.formItemSwitch }>
            <Switch
              checked={ settings.useExisting !== false }
              labelRight="Use existing"
              onChange={ (v) => { update('useExisting', v) } }
              size="small"
            />
          </Form.Item>

          <Form.Item className={ styles.formItemSwitch }>
            <Switch
              checked={ settings.overwriteExisting !== false }
              labelRight="Overwrite existing"
              onChange={ (v) => { update('overwriteExisting', v) } }
              size="small"
            />
          </Form.Item>

          <Form.Item
            className={ styles.formItemLast }
            label={ <span className={ styles.label }>Preg match</span> }
          >
            <Input
              onChange={ (e) => { update('pregMatch', e.target.value) } }
              value={ settings.pregMatch ?? '' }
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
