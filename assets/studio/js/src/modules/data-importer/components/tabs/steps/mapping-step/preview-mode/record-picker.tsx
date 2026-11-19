/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo } from 'react'
import { Button, Flex, IconButton, Modal, Space, Spin } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { normalizeDataRow } from '../../../../../utils/normalize-data-row'
import { usePreviewRecordQuery } from '../../shared/use-preview-record-query'
import { useMappingPreviewMode } from './mapping-preview-mode'
import { useStyles } from './preview-mode.styles'

// the columns a record is told apart by
const SHOWN_COLUMNS = 5

export interface RecordPickerProps {
  onClose: () => void
}

/** Browses the records of the preview file, one at a time, and makes the shown one the selected record */
export const RecordPicker = ({ onClose }: RecordPickerProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { recordNumber, setRecordNumber, getCurrentConfig } = useMappingPreviewMode()
  const { dataPreview, currentRecordIndex, isFetching, load } = usePreviewRecordQuery({ enabled: true, getCurrentConfig })

  useEffect(() => {
    load(recordNumber)
  }, [])

  const columns = useMemo(() => dataPreview.map(normalizeDataRow).slice(0, SHOWN_COLUMNS), [dataPreview])

  const handleSelect = (): void => {
    setRecordNumber(currentRecordIndex)
    onClose()
  }

  return (
    <Modal
      footer={ (
        <Flex
          gap="small"
          justify="flex-end"
        >
          <Button onClick={ onClose }>
            { t('data-importer.mapping.preview-mode.picker.cancel') }
          </Button>
          <Button
            disabled={ isFetching || columns.length === 0 }
            onClick={ handleSelect }
            type="primary"
          >
            { t('data-importer.mapping.preview-mode.picker.select') }
          </Button>
        </Flex>
      ) }
      onCancel={ onClose }
      open
      title={ t('data-importer.mapping.preview-mode.preview-item') }
      width={ 520 }
    >
      <div className={ styles.pickerHeader }>
        <span>{ t('data-importer.mapping.preview-mode.record', { number: currentRecordIndex + 1 }) }</span>
        <Space size="mini">
          { isFetching && (
            <Spin
              size="small"
              type="classic"
            />
          ) }
          <IconButton
            disabled={ isFetching || currentRecordIndex === 0 }
            icon={ { value: 'chevron-left' } }
            onClick={ () => { load(currentRecordIndex - 1) } }
            size="small"
            tooltip={ { title: t('data-importer.preview-import.prev') } }
            type="default"
          />
          <IconButton
            disabled={ isFetching }
            icon={ { value: 'chevron-right' } }
            onClick={ () => { load(currentRecordIndex + 1) } }
            size="small"
            tooltip={ { title: t('data-importer.preview-import.next') } }
            type="default"
          />
        </Space>
      </div>

      <dl className={ styles.pickerColumns }>
        { columns.map((column) => (
          <React.Fragment key={ column.dataIndex }>
            <dt className={ styles.pickerLabel }>{ column.label }</dt>
            <dd
              className={ styles.pickerValue }
              title={ column.value }
            >
              { column.value }
            </dd>
          </React.Fragment>
        )) }
      </dl>
    </Modal>
  )
}
