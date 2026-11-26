/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useRef, useState } from 'react'
import { IconButton, InputNumber } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useMappingPreviewMode } from './mapping-preview-mode'
import { useStyles } from './preview-mode.styles'

// a typed number or a click on ‹ › selects its record this long after the last one; Enter and leaving the field at once
export const RECORD_INPUT_DEBOUNCE_MS = 400

/** "Record [20] of 150 ‹ ›": the selected record's one-based number, typed or stepped, kept within 1..N */
export const RecordNumberField = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { recordNumber, setRecordNumber, recordCount } = useMappingPreviewMode()
  const selected = recordNumber + 1
  // what the field shows: the selected record, or what is typed or stepped to until it settles
  const [draft, setDraft] = useState<number | null>(selected)
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  // the selected record changed, e.g. to the last one after a number past it: the field follows
  useEffect(() => {
    setDraft(recordNumber + 1)
  }, [recordNumber])

  const cancelPending = (): void => {
    clearTimeout(timerRef.current)
  }

  useEffect(() => cancelPending, [])

  const isValid = (value: number | null): value is number =>
    value !== null && Number.isInteger(value) && value >= 1 && (recordCount === null || value <= recordCount)

  const clamp = (value: number): number => Math.min(Math.max(Math.round(value), 1), recordCount ?? Number.MAX_SAFE_INTEGER)

  const select = (value: number): void => {
    cancelPending()
    setDraft(value)
    setRecordNumber(value - 1)
  }

  // only the last of quick keystrokes or clicks selects its record, so one request goes out for it
  const selectLater = (value: number): void => {
    cancelPending()
    timerRef.current = setTimeout(() => { setRecordNumber(value - 1) }, RECORD_INPUT_DEBOUNCE_MS)
  }

  const handleChange = (value: number | null): void => {
    setDraft(value)
    if (isValid(value)) {
      selectLater(value)
    } else {
      cancelPending()
    }
  }

  // Enter or leaving the field: a number out of range is kept within 1..N, an empty field shows the selected record again
  const settle = (): void => {
    if (draft === null) {
      cancelPending()
      setDraft(selected)
      return
    }
    select(clamp(draft))
  }

  const step = (delta: number): void => {
    const value = clamp((isValid(draft) ? draft : selected) + delta)
    setDraft(value)
    selectLater(value)
  }

  const current = isValid(draft) ? draft : selected

  return (
    <>
      <span>{ t('data-importer.mapping.preview-mode.record-label') }</span>
      <InputNumber
        aria-label={ t('data-importer.mapping.preview-mode.record-label') }
        className={ styles.recordInput }
        controls={ false }
        onBlur={ settle }
        onChange={ handleChange }
        onPressEnter={ settle }
        size="small"
        status={ isValid(draft) ? undefined : 'error' }
        value={ draft }
      />
      { recordCount !== null && (
        <span>{ t('data-importer.mapping.preview-mode.of-records', { total: recordCount }) }</span>
      ) }
      <IconButton
        disabled={ current <= 1 }
        icon={ { value: 'chevron-left' } }
        onClick={ () => { step(-1) } }
        size="small"
        tooltip={ { title: t('data-importer.preview-import.prev') } }
        type="default"
      />
      <IconButton
        disabled={ recordCount !== null && current >= recordCount }
        icon={ { value: 'chevron-right' } }
        onClick={ () => { step(1) } }
        size="small"
        tooltip={ { title: t('data-importer.preview-import.next') } }
        type="default"
      />
    </>
  )
}
