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
import { Form, TextArea } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { TransformerSettingsLayout } from '../transformer-settings-layout'
import { useStyles } from './symfony-expression-transformer-form.styles'

export interface SymfonyExpressionTransformerConfig {
  expression?: string
}

interface SymfonyExpressionTransformerFormProps {
  settings: SymfonyExpressionTransformerConfig
  onChange: (settings: SymfonyExpressionTransformerConfig) => void
}

export const isExpressionMissing = (settings: SymfonyExpressionTransformerConfig): boolean =>
  (settings.expression ?? '').trim() === ''

const TRANSLATION_PREFIX = 'data-importer.mapping.advanced-modal.transformer.symfony-expression'

export const SymfonyExpressionTransformerForm = (
  { settings, onChange }: SymfonyExpressionTransformerFormProps
): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles: ownStyles } = useStyles()
  const missing = isExpressionMissing(settings)

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <Form.Item
          className={ styles.formItemLast }
          extra={ <span className={ ownStyles.help }>{ t(`${TRANSLATION_PREFIX}.help`) }</span> }
          help={ missing ? t(`${TRANSLATION_PREFIX}.required`) : undefined }
          label={ <span className={ styles.label }>{ t(`${TRANSLATION_PREFIX}.expression`) }</span> }
          required
          validateStatus={ missing ? 'error' : undefined }
        >
          <TextArea
            className={ ownStyles.expression }
            onChange={ (e) => { onChange({ ...settings, expression: e.target.value }) } }
            placeholder="attributes[0] == 'Demo Value' ? attributes[1] : null"
            rows={ 4 }
            value={ settings.expression ?? '' }
          />
        </Form.Item>
      ) }
    </TransformerSettingsLayout>
  )
}
