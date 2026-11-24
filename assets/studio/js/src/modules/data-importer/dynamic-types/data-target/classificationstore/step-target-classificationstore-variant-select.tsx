/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Select } from '@pimcore/studio-ui-bundle/components'
import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useStyles } from '../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target.styles'
import { splitKeyReference, withVariant } from './classification-store-variant'

export interface StepTargetClassificationstoreVariantSelectProps {
  keyId: string
  variants: string[]
  onChange: (keyId: string) => void
}

export function StepTargetClassificationstoreVariantSelect ({
  keyId,
  variants,
  onChange
}: Readonly<StepTargetClassificationstoreVariantSelectProps>): React.JSX.Element {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const options = [
    { value: '', label: t('data-importer.mapping.advanced-modal.step-target.classification-store-variant-default') },
    ...variants.map((variant) => ({ value: variant, label: variant }))
  ]

  return (
    <div>
      <div className={ styles.fieldLabel }>
        {t('data-importer.mapping.advanced-modal.step-target.classification-store-variant')}
      </div>
      <div className={ styles.selectSkeletonWrapper }>
        <Select
          className={ styles.selectFull }
          onChange={ (variant: string) => { onChange(withVariant(keyId, variant)) } }
          options={ options }
          value={ splitKeyReference(keyId).variant ?? '' }
        />
      </div>
    </div>
  )
}
