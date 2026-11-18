/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { Form, PreviewModeProvider, usePreviewMode } from '@pimcore/studio-ui-bundle/components'
import { type BackendConfiguration } from '../../../../../utils/transformers'
import { normalizeDataRow, type DataRow } from '../../../../../utils/normalize-data-row'
import { usePreviewRecordQuery } from '../../shared/use-preview-record-query'

// the rows' one transformation request waits this long after the last edit or record change
export const PREVIEW_REQUEST_DEBOUNCE_MS = 300

export interface MappingPreviewRequest {
  currentConfig: BackendConfiguration
  recordNumber: number
}

export interface MappingPreviewModeValue {
  enabled: boolean
  setEnabled: (enabled: boolean) => void
  // false while the source has no preview record to run the mappings on
  available: boolean
  // zero-based record of the preview file the mappings run on
  recordNumber: number
  setRecordNumber: (recordNumber: number) => void
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
  recordNumber: 0,
  setRecordNumber: () => undefined,
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
  getCurrentConfig: () => BackendConfiguration
  children: React.ReactNode
}

const MappingPreviewModeState = ({ available, getCurrentConfig, children }: MappingPreviewModeStateProps): React.JSX.Element => {
  const { enabled: switchedOn, setEnabled, item, setItem, fallbackItem } = usePreviewMode()
  const enabled = switchedOn && available
  const recordNumber = Number(item?.data.id ?? fallbackItem?.id ?? 0)
  const [previewRequest, setPreviewRequest] = useState<MappingPreviewRequest | null>(null)
  const [previewPending, setPreviewPending] = useState(false)
  const [previewError, setPreviewError] = useState<string | null>(null)

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

  const { dataPreview, load } = usePreviewRecordQuery({ enabled, getCurrentConfig })

  useEffect(() => {
    if (enabled) load(recordNumber)
  }, [enabled, recordNumber, load])

  const record = useMemo(() => (enabled ? dataPreview.map(normalizeDataRow) : []), [enabled, dataPreview])

  const value = useMemo((): MappingPreviewModeValue => ({
    enabled,
    setEnabled,
    available,
    recordNumber,
    setRecordNumber,
    record,
    previewRequest,
    previewPending,
    previewError,
    setPreviewError,
    getCurrentConfig
  }), [enabled, setEnabled, available, recordNumber, setRecordNumber, record, previewRequest, previewPending, previewError, getCurrentConfig])

  return (
    <MappingPreviewModeContext.Provider value={ value }>
      { children }
    </MappingPreviewModeContext.Provider>
  )
}

export interface MappingPreviewModeProviderProps {
  // the source has a preview record (an uploaded preview file or a posted record)
  available: boolean
  // the form's configuration, unsaved edits included
  getCurrentConfig: () => BackendConfiguration
  children: React.ReactNode
}

// off on each open: nothing of it is stored; the "Sources with preview" panel shows the first record
const FALLBACK_RECORD = { id: 0 }

export const MappingPreviewModeProvider = ({ available, getCurrentConfig, children }: MappingPreviewModeProviderProps): React.JSX.Element => (
  <PreviewModeProvider fallbackItem={ FALLBACK_RECORD }>
    <MappingPreviewModeState
      available={ available }
      getCurrentConfig={ getCurrentConfig }
    >
      { children }
    </MappingPreviewModeState>
  </PreviewModeProvider>
)
