/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { Form, PreviewModeProvider, usePreviewMode } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type BackendConfiguration } from '../../../../../utils/transformers'
import { normalizeDataRow, type DataRow } from '../../../../../utils/normalize-data-row'
import { apiErrorMessage, isNotFoundError } from '../../../../../utils/api-error-message'
import { usePreviewRecordQuery } from '../../shared/use-preview-record-query'

// the rows' one transformation request waits this long after the last edit or record change
export const PREVIEW_REQUEST_DEBOUNCE_MS = 300

export interface MappingPreviewRequest {
  currentConfig: BackendConfiguration
  recordNumber: number
}

export interface MappingPreviewModeValue {
  // switched on and running on preview data
  enabled: boolean
  setEnabled: (enabled: boolean) => void
  // false while the source has no preview record to run the mappings on
  available: boolean
  // switched on, but no preview file was uploaded or copied yet: a hint instead of the previews
  noPreviewData: boolean
  // switched on, but reading the preview data failed otherwise (e.g. an invalid preview file): its error
  // instead of the hint
  loadError: string | null
  // a request found no preview data (404)
  markNoPreviewData: () => void
  // a preview file was uploaded or copied: read it again and run the previews
  previewDataChanged: () => void
  // zero-based record of the preview file the mappings run on
  recordNumber: number
  setRecordNumber: (recordNumber: number) => void
  // the number of records in the preview data, once an answer told it (a record past the last one is answered
  // with the last one); null until then
  recordCount: number | null
  // the selected record's columns, empty until loaded
  record: DataRow[]
  // the form's current configuration and the record, debounced; null while preview mode is off
  previewRequest: MappingPreviewRequest | null
  // a mapping or the record changed and its request waits for the debounce
  previewPending: boolean
  previewError: string | null
  setPreviewError: (message: string | null) => void
  getCurrentConfig: () => BackendConfiguration
}

const inactive: MappingPreviewModeValue = {
  enabled: false,
  setEnabled: () => undefined,
  available: false,
  noPreviewData: false,
  loadError: null,
  markNoPreviewData: () => undefined,
  previewDataChanged: () => undefined,
  recordNumber: 0,
  setRecordNumber: () => undefined,
  recordCount: null,
  record: [],
  previewRequest: null,
  previewPending: false,
  previewError: null,
  setPreviewError: () => undefined,
  getCurrentConfig: () => ({})
}

const MappingPreviewModeContext = createContext<MappingPreviewModeValue>(inactive)

/** Preview mode of the mapping step; an inactive one outside its provider */
export const useMappingPreviewMode = (): MappingPreviewModeValue => useContext(MappingPreviewModeContext)

interface MappingPreviewModeStateProps {
  available: boolean
  loadError?: unknown
  getCurrentConfig: () => BackendConfiguration
  onPreviewDataChange: () => void
  children: React.ReactNode
}

const MappingPreviewModeState = ({ available, loadError: loadFailure, getCurrentConfig, onPreviewDataChange, children }: MappingPreviewModeStateProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { enabled: switchedOn, setEnabled, item, setItem, fallbackItem } = usePreviewMode()
  // a preview request answered that the configuration has no preview data
  const [previewDataMissing, setPreviewDataMissing] = useState(false)
  // only a missing preview file (404) asks for an upload or copy; any other failure is shown as it is
  const loadError = switchedOn && loadFailure !== undefined && loadFailure !== null && !isNotFoundError(loadFailure)
    ? apiErrorMessage(loadFailure) ?? t('data-importer.mapping.preview-mode.unavailable')
    : null
  const noPreviewData = switchedOn && loadError === null && (!available || previewDataMissing)
  const enabled = switchedOn && !noPreviewData && loadError === null
  const recordNumber = Number(item?.data.id ?? fallbackItem?.id ?? 0)
  const [previewRequest, setPreviewRequest] = useState<MappingPreviewRequest | null>(null)
  const [previewPending, setPreviewPending] = useState(false)
  const [previewError, setPreviewError] = useState<string | null>(null)
  const [recordCount, setRecordCount] = useState<number | null>(null)

  // serialized, so only a changed value of the form starts a new request
  const formFingerprint = Form.useWatch((values: object) => JSON.stringify(values), { preserve: true }) as string | undefined

  const setRecordNumber = useCallback((next: number): void => {
    setItem({ data: { id: next } })
  }, [setItem])

  useEffect(() => {
    if (!enabled) {
      setPreviewRequest(null)
      setPreviewPending(false)
      setPreviewError(null)
      return
    }

    setPreviewPending(true)
    const timer = setTimeout(() => {
      setPreviewRequest({ currentConfig: getCurrentConfig(), recordNumber })
      setPreviewPending(false)
    }, PREVIEW_REQUEST_DEBOUNCE_MS)

    return () => { clearTimeout(timer) }
  }, [enabled, recordNumber, formFingerprint, getCurrentConfig])

  // the record is read while the source has preview records, so a 404 on it shows the hint
  const recordQueryEnabled = switchedOn && available
  const { dataPreview, currentRecordIndex, error: recordError, isFetching: isRecordFetching, load } = usePreviewRecordQuery({ enabled: recordQueryEnabled, getCurrentConfig })

  // the record asked for last, and the answer shown when it was asked: only a later answer belongs to it
  const askedRecordRef = useRef<{ recordNumber: number, answerBefore: unknown } | null>(null)
  useEffect(() => {
    if (!recordQueryEnabled) return
    askedRecordRef.current = { recordNumber, answerBefore: dataPreview }
    load(recordNumber)
  }, [recordQueryEnabled, recordNumber, load])

  // a record past the last one is answered with the last one: that tells the number of records, and the
  // last one becomes the selected record
  useEffect(() => {
    const asked = askedRecordRef.current
    if (asked === null || dataPreview === asked.answerBefore || dataPreview.length === 0) return
    if (currentRecordIndex < asked.recordNumber) {
      setRecordCount(currentRecordIndex + 1)
      setRecordNumber(currentRecordIndex)
    }
  }, [dataPreview, currentRecordIndex, setRecordNumber])

  // a 404 counts once the record was asked for since: until then the query shows an earlier answer,
  // e.g. the one from before a copy or upload
  const recordAskedRef = useRef(false)
  useEffect(() => {
    if (!recordQueryEnabled) {
      recordAskedRef.current = false
      return
    }
    if (isRecordFetching) {
      recordAskedRef.current = true
      return
    }
    if (recordAskedRef.current && isNotFoundError(recordError)) setPreviewDataMissing(true)
  }, [recordQueryEnabled, isRecordFetching, recordError])

  // switched on again, the preview data is looked for anew
  useEffect(() => {
    if (!switchedOn) setPreviewDataMissing(false)
  }, [switchedOn])

  const markNoPreviewData = useCallback((): void => {
    setPreviewDataMissing(true)
  }, [])

  const previewDataChanged = useCallback((): void => {
    recordAskedRef.current = false
    setPreviewDataMissing(false)
    setRecordCount(null)
    onPreviewDataChange()
    if (recordQueryEnabled) load(recordNumber, { forceRefetch: true })
  }, [onPreviewDataChange, recordQueryEnabled, recordNumber, load])

  const record = useMemo(() => (enabled ? dataPreview.map(normalizeDataRow) : []), [enabled, dataPreview])

  const value = useMemo((): MappingPreviewModeValue => ({
    enabled,
    setEnabled,
    available,
    noPreviewData,
    loadError,
    markNoPreviewData,
    previewDataChanged,
    recordNumber,
    setRecordNumber,
    recordCount,
    record,
    previewRequest,
    previewPending,
    previewError,
    setPreviewError,
    getCurrentConfig
  }), [enabled, setEnabled, available, noPreviewData, loadError, markNoPreviewData, previewDataChanged, recordNumber, setRecordNumber, recordCount, record, previewRequest, previewPending, previewError, getCurrentConfig])

  return (
    <MappingPreviewModeContext.Provider value={ value }>
      { children }
    </MappingPreviewModeContext.Provider>
  )
}

export interface MappingPreviewModeProviderProps {
  // the source has a preview record (an uploaded preview file or a posted record)
  available: boolean
  // why reading the preview data failed, if it did: a 404 means no preview file yet
  loadError?: unknown
  // the form's configuration, unsaved edits included
  getCurrentConfig: () => BackendConfiguration
  // a preview file was uploaded or copied: the step reads it again
  onPreviewDataChange: () => void
  children: React.ReactNode
}

// off on each open: nothing of it is stored; the "Sources with preview" panel shows the first record
const FALLBACK_RECORD = { id: 0 }

export const MappingPreviewModeProvider = ({ available, loadError, getCurrentConfig, onPreviewDataChange, children }: MappingPreviewModeProviderProps): React.JSX.Element => (
  <PreviewModeProvider fallbackItem={ FALLBACK_RECORD }>
    <MappingPreviewModeState
      available={ available }
      getCurrentConfig={ getCurrentConfig }
      loadError={ loadError }
      onPreviewDataChange={ onPreviewDataChange }
    >
      { children }
    </MappingPreviewModeState>
  </PreviewModeProvider>
)
