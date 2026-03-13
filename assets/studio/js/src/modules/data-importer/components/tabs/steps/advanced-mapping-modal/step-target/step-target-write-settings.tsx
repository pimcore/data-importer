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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from './step-target.styles'

export interface StepTargetWriteSettingsProps {
  showWriteSettings: boolean
  hasTypeError: boolean
  showOverwriteMode: boolean
  dataTarget: MappingConfigItem['dataTarget']
  onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void
}

export const StepTargetWriteSettings = ({
  showWriteSettings,
  hasTypeError,
  showOverwriteMode,
  dataTarget,
  onDataTargetChange
}: StepTargetWriteSettingsProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  if (!showWriteSettings || hasTypeError) {
    return null
  }

  return (
    <>
      <div className={ styles.overwriteLabel }>
        { t('data-importer.mapping.advanced-modal.step-target.overwrite') }
      </div>

      <Switch
        checked={ dataTarget?.settings?.writeIfTargetIsNotEmpty ?? true }
        labelRight={ <span className={ styles.switchLabel }>{ t('data-importer.mapping.advanced-modal.write-if-target-not-empty') }</span> }
        onChange={ (checked) => {
          onDataTargetChange({
            ...dataTarget,
            settings: {
              ...dataTarget?.settings,
              writeIfTargetIsNotEmpty: checked,
              writeIfSourceIsEmpty: !!checked
            }
          })
        } }
        size="small"
        tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-target-not-empty.tooltip') }
      />

      { showOverwriteMode && (
        <div>
          <div className={ styles.fieldLabel }>
            { t('data-importer.mapping.advanced-modal.step-target.overwrite-mode') }
          </div>
          <div className={ styles.selectSkeletonWrapper }>
            <Select
              className={ styles.selectFull }
              onChange={ (v: string) => {
                onDataTargetChange({
                  ...dataTarget,
                  settings: { ...dataTarget?.settings, overwriteMode: v }
                })
              } }
              options={ [
                { value: 'replace', label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.replace') },
                { value: 'merge', label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.merge') }
              ] }
              value={ dataTarget?.settings?.overwriteMode }
            />
          </div>
        </div>
      ) }

      <Switch
        checked={ dataTarget?.settings?.writeIfSourceIsEmpty ?? true }
        disabled={ !(dataTarget?.settings?.writeIfTargetIsNotEmpty ?? true) }
        labelRight={ <span className={ styles.switchLabel }>{ t('data-importer.mapping.advanced-modal.write-if-source-empty') }</span> }
        onChange={ (checked) => {
          onDataTargetChange({
            ...dataTarget,
            settings: { ...dataTarget?.settings, writeIfSourceIsEmpty: checked }
          })
        } }
        size="small"
        tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip') }
      />
    </>
  )
}
