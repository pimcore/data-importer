/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useState } from 'react'
import { Button, Switch, Text, Tooltip } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useMappingPreviewMode } from './mapping-preview-mode'
import { RecordPicker } from './record-picker'
import { useStyles } from './preview-mode.styles'

export interface PreviewModeLineProps {
  showCollapseAll: boolean
  // every mapping is folded: the button offers "Expand all"
  allCollapsed: boolean
  onCollapseAll: () => void
}

const SelectedRecord = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { recordNumber, record } = useMappingPreviewMode()
  // the record is told apart by its first source column
  const value = record[0]?.value

  return (
    <span className={ styles.record }>
      <span>{ t('data-importer.mapping.preview-mode.record', { number: recordNumber + 1 }) }</span>
      { value !== undefined && value !== '' && (
        <>
          <span className={ styles.separator }>{ '│' }</span>
          <Tooltip title={ value }>
            <span
              className={ styles.recordValue }
              data-record-value
            >
              { value }
            </span>
          </Tooltip>
        </>
      ) }
    </span>
  )
}

/** The line below the "Mappings" headline: the preview mode toggle, the selected record and "Collapse all" */
export const PreviewModeLine = ({ showCollapseAll, allCollapsed, onCollapseAll }: PreviewModeLineProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { enabled, setEnabled, available, previewError } = useMappingPreviewMode()
  const [pickerOpen, setPickerOpen] = useState(false)

  const toggle = (
    <Switch
      aria-label={ t('data-importer.mapping.preview-mode.switch') }
      checked={ enabled }
      disabled={ !available }
      labelRight={ t('data-importer.mapping.preview-mode.switch') }
      onChange={ setEnabled }
      size="small"
    />
  )

  return (
    <div
      aria-label={ t('data-importer.mapping.preview-mode.switch') }
      className={ styles.line }
      role="toolbar"
    >
      { available
        ? toggle
        : (
          // a disabled switch takes no pointer events, its wrapper shows the hint
          <Tooltip title={ t('data-importer.mapping.preview-mode.no-preview-data') }>
            <span>{ toggle }</span>
          </Tooltip>
          ) }

      { enabled && (
        <>
          <SelectedRecord />

          <Button onClick={ () => { setPickerOpen(true) } }>
            { t('data-importer.mapping.preview-mode.preview-item') }
          </Button>

          { previewError !== null && (
            <Text
              className={ styles.error }
              type="danger"
            >
              { previewError }
            </Text>
          ) }
        </>
      ) }

      { showCollapseAll && (
        <Button
          className={ styles.collapseAll }
          onClick={ onCollapseAll }
          size="small"
          type="link"
        >
          { allCollapsed
            ? t('data-importer.mapping.expand-all')
            : t('data-importer.mapping.collapse-all') }
        </Button>
      ) }

      { enabled && pickerOpen && (
        <RecordPicker onClose={ () => { setPickerOpen(false) } } />
      ) }
    </div>
  )
}
