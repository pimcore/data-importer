/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { type DataTargetConfig } from '../../../../../types'
import { useStyles } from './step-target.styles'

export interface StepTargetWriteSettingsProps {
  settings: DataTargetConfig['settings']
  onChange: (settings: DataTargetConfig['settings']) => void
  showOverwriteMode?: boolean
}

export const StepTargetWriteSettings = ({
  showOverwriteMode = false,
  settings,
  onChange
}: StepTargetWriteSettingsProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  useEffect(() => {
    if (
      settings?.writeIfTargetIsNotEmpty !== true &&
            (settings?.overwriteMode !== undefined || settings?.writeIfSourceIsEmpty === true)
    ) {
      onChange({ ...settings, overwriteMode: undefined, writeIfSourceIsEmpty: false })
    }
  }, [settings?.writeIfTargetIsNotEmpty, settings?.overwriteMode, settings?.writeIfSourceIsEmpty])

  return (
    <>
      <div className={ styles.overwriteLabel }>
        {t('data-importer.mapping.advanced-modal.step-target.overwrite')}
      </div>

      <Switch
        checked={ settings?.writeIfTargetIsNotEmpty ?? true }
        labelRight={
          <span className={ styles.switchLabel }>
            {t('data-importer.mapping.advanced-modal.write-if-target-not-empty')}
          </span>
                }
        onChange={ (checked) => {
          onChange({
            ...settings,
            writeIfTargetIsNotEmpty: checked,
            writeIfSourceIsEmpty: checked
          })
        } }
        size="small"
        tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-target-not-empty.tooltip') }
      />

      {showOverwriteMode && (
        <div>
          <div className={ styles.fieldLabel }>
            {t('data-importer.mapping.advanced-modal.step-target.overwrite-mode')}
          </div>
          <div className={ styles.selectSkeletonWrapper }>
            <Select
              className={ styles.selectFull }
              onChange={ (value) => {
                onChange({ ...settings, overwriteMode: value })
              } }
              options={ [
                {
                  value: 'replace',
                  label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.replace')
                },
                {
                  value: 'merge',
                  label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.merge')
                }
              ] }
              value={ settings?.overwriteMode }
            />
          </div>
        </div>
      )}

      <Switch
        checked={ settings?.writeIfSourceIsEmpty ?? true }
        disabled={ !(settings?.writeIfTargetIsNotEmpty ?? true) }
        labelRight={
          <span className={ styles.switchLabel }>
            {t('data-importer.mapping.advanced-modal.write-if-source-empty')}
          </span>
                }
        onChange={ (checked) => {
          onChange({ ...settings, writeIfSourceIsEmpty: checked })
        } }
        size="small"
        tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip') }
      />
    </>
  )
}
