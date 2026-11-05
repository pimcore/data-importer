/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Switch } from '@pimcore/studio-ui-bundle/components'
import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useStyles } from '../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target.styles'
import { type DataTargetConfig } from '../../../types'
import {
  type ClassificationStoreTargetId,
  resolveWriteIfSourceIsEmpty,
  withWriteIfSourceIsEmpty
} from './classification-store-write-settings'

export interface StepTargetClassificationstoreWriteSettingsProps {
  readonly settings: DataTargetConfig['settings']
  readonly target: ClassificationStoreTargetId
  readonly onChange: (settings: DataTargetConfig['settings']) => void
}

export function StepTargetClassificationstoreWriteSettings ({
  settings,
  target,
  onChange
}: Readonly<StepTargetClassificationstoreWriteSettingsProps>): React.JSX.Element {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <>
      <div className={ styles.overwriteLabel }>
        {t('data-importer.mapping.advanced-modal.step-target.overwrite')}
      </div>

      <Switch
        checked={ resolveWriteIfSourceIsEmpty(settings, target) }
        labelRight={
          <span className={ styles.switchLabel }>
            {t('data-importer.mapping.advanced-modal.write-if-source-empty')}
          </span>
                }
        onChange={ (checked) => {
          onChange(withWriteIfSourceIsEmpty(settings, checked))
        } }
        size="small"
        tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip') }
      />
    </>
  )
}
