/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useId } from 'react'
import { Button, Switch, Text, Tooltip } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useMappingSource } from '../../mapping-source/mapping-source'
import { usePreviewDataActions } from '../../shared/use-preview-data-actions'
import { useMappingPreviewMode } from './mapping-preview-mode'
import { RecordNumberField } from './record-number-field'
import { useStyles } from './preview-mode.styles'

export interface PreviewModeLineProps {
  showCollapseAll: boolean
  // every mapping is folded: the button offers "Expand all"
  allCollapsed: boolean
  onCollapseAll: () => void
}

const SelectedRecord = ({ fieldId }: { fieldId: string }): React.JSX.Element => {
  const { styles } = useStyles()
  const { record } = useMappingPreviewMode()
  // the record is told apart by its first source column
  const value = record[0]?.value

  return (
    <span className={ styles.record }>
      <RecordNumberField id={ fieldId } />
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

/** Preview mode without preview data: what to do instead of an error, with the Preview Import step's own actions */
const NoPreviewDataHint = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { configName } = useMappingSource()
  const { getCurrentConfig, previewDataChanged } = useMappingPreviewMode()
  const { upload, copyFromSource, isCopying, uploadModal } = usePreviewDataActions({
    configName,
    getCurrentConfig,
    onPreviewDataChange: previewDataChanged
  })

  return (
    <span
      className={ styles.hint }
      role="status"
    >
      <span className={ styles.hintText }>{ t('data-importer.mapping.preview-mode.no-preview-data') }</span>
      { configName !== undefined && (
        <>
          <Button
            onClick={ upload }
            size="small"
            type="link"
          >
            { t('data-importer.preview-import.upload-file') }
          </Button>
          <Button
            loading={ isCopying }
            onClick={ () => { void copyFromSource() } }
            size="small"
            type="link"
          >
            { t('data-importer.preview-import.copy-from-source') }
          </Button>
          { uploadModal }
        </>
      ) }
    </span>
  )
}

/** The line below the "Mappings" headline: the preview mode toggle, the selected record and "Collapse all" */
export const PreviewModeLine = ({ showCollapseAll, allCollapsed, onCollapseAll }: PreviewModeLineProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { enabled, setEnabled, noPreviewData, previewError } = useMappingPreviewMode()
  const recordFieldId = useId()

  const focusRecordField = (): void => {
    const field = document.getElementById(recordFieldId)
    if (field instanceof HTMLInputElement) {
      field.focus()
      field.select()
    }
  }

  return (
    <div
      aria-label={ t('data-importer.mapping.preview-mode.switch') }
      className={ styles.line }
      role="toolbar"
    >
      { /* usable without preview data too: switched on, it says how to provide some */ }
      <Switch
        aria-label={ t('data-importer.mapping.preview-mode.switch') }
        checked={ enabled || noPreviewData }
        labelRight={ t('data-importer.mapping.preview-mode.switch') }
        onChange={ setEnabled }
        size="small"
      />

      { noPreviewData && <NoPreviewDataHint /> }

      { enabled && (
        <>
          <SelectedRecord fieldId={ recordFieldId } />

          <Button onClick={ focusRecordField }>
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
    </div>
  )
}
