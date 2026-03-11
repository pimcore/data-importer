/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useState } from 'react'
import { theme } from 'antd'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { IconButton, Input, Spin } from '@pimcore/studio-ui-bundle/components'
import {
  useBundleDataImporterConfigLoadTransformationResultQuery
} from '../../../../../data-importer-api-slice.gen'
import { normalizeDataRow, type DataRow } from '../../../../../utils/normalize-data-row'
import { type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig, type MappingConfigItem } from '../../../../../types'
import { useStyles } from './preview-panel.styles'
import { usePreviewRecordQuery } from '../../shared/use-preview-record-query'

/* ── Prop types ──────────────────────────────────────────────────────────── */

interface ImportModeProps {
  mode: 'import'
  configName: string
  selectedDataSourceIndex: string[]
  refreshToken?: never
  currentMappingItem?: never
  baseConfig?: never
}

interface ResultModeProps {
  mode: 'result'
  configName: string
  refreshToken?: number
  currentMappingItem?: MappingConfigItem
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  selectedDataSourceIndex?: never
}

export type PreviewPanelProps = ImportModeProps | ResultModeProps

/* ── Component ───────────────────────────────────────────────────────────── */

export const PreviewPanel = (props: PreviewPanelProps): React.JSX.Element => {
  const { token } = theme.useToken()
  const { t } = useTranslation()
  const { styles, cx } = useStyles()

  /* ── import mode state ── */
  const [rows, setRows] = useState<DataRow[]>([])
  const [importRecordNumber, setImportRecordNumber] = useState(0)
  const [actualRecordIndex, setActualRecordIndex] = useState(0)
  const [searchText, setSearchText] = useState('')
  const {
    dataPreview: importPreviewRows,
    currentRecordIndex: importCurrentRecordIndex,
    isLoading: importLoading,
    isFetching: isImportFetching,
    isError: isImportError,
    load: fetchImportPreview
  } = usePreviewRecordQuery({
    configName: props.configName,
    enabled: props.mode === 'import'
  })

  /* ── result mode state ── */
  const [previews, setPreviews] = useState<string[]>([])
  const [resultRecordNumber, setResultRecordNumber] = useState(0)
  const [resultRequest, setResultRequest] = useState<{
    name: string
    bundleDataImporterLoadPreviewParameters: {
      recordNumber: number
      currentConfig?: Record<string, object>
    }
  } | undefined>(undefined)
  const {
    data: resultPreviewResponse,
    isLoading: isResultLoading,
    isFetching: isResultFetching,
    isError: isResultError
  } = useBundleDataImporterConfigLoadTransformationResultQuery(
    resultRequest as NonNullable<typeof resultRequest>,
    {
      skip: props.mode !== 'result' || resultRequest === undefined,
      refetchOnMountOrArgChange: false
    }
  )

  useEffect(() => {
    setRows(importPreviewRows.map((row) => normalizeDataRow(row)))
  }, [importPreviewRows])

  useEffect(() => {
    setActualRecordIndex(importCurrentRecordIndex)
  }, [importCurrentRecordIndex])

  useEffect(() => {
    if (!isImportError) return
    setRows([])
  }, [isImportError])

  /* ── result mode fetch ── */
  const fetchResultPreview = (record: number): void => {
    const currentConfig: Record<string, object> | undefined = props.currentMappingItem !== undefined
      ? {
          mappingConfig: [props.currentMappingItem] as object[],
          ...(props.baseConfig?.loaderConfig !== undefined && { loaderConfig: props.baseConfig.loaderConfig as object }),
          ...(props.baseConfig?.interpreterConfig !== undefined && { interpreterConfig: props.baseConfig.interpreterConfig as object }),
          ...(props.baseConfig?.resolverConfig !== undefined && { resolverConfig: props.baseConfig.resolverConfig as object }),
          ...(props.baseConfig?.processingConfig !== undefined && { processingConfig: props.baseConfig.processingConfig as object })
        }
      : undefined

    setResultRequest({
      name: props.configName,
      bundleDataImporterLoadPreviewParameters: {
        recordNumber: record,
        ...(currentConfig !== undefined && { currentConfig })
      }
    })
  }

  useEffect(() => {
    if (props.mode !== 'result') return
    if (resultPreviewResponse === undefined) return
    const rawPreviews = resultPreviewResponse.transformationResultPreviews ?? []
    setPreviews(props.currentMappingItem !== undefined ? rawPreviews.slice(0, 1) : rawPreviews)
  }, [props.mode, resultPreviewResponse, props.currentMappingItem])

  useEffect(() => {
    if (!isResultError) return
    setPreviews([])
  }, [isResultError])

  useEffect(() => {
    if (props.mode !== 'result') return
    fetchResultPreview(resultRecordNumber)
  }, [props.refreshToken])

  /* ── import mode handlers ── */
  const handleImportPrev = (): void => {
    const next = Math.max(0, importRecordNumber - 1)
    setImportRecordNumber(next)
    fetchImportPreview(next)
  }

  const handleImportNext = (): void => {
    const next = importRecordNumber + 1
    setImportRecordNumber(next)
    fetchImportPreview(next)
  }

  /* ── result mode handlers ── */
  const handleResultPrev = (): void => {
    const next = Math.max(0, resultRecordNumber - 1)
    setResultRecordNumber(next)
    fetchResultPreview(next)
  }

  const handleResultNext = (): void => {
    const next = resultRecordNumber + 1
    setResultRecordNumber(next)
    fetchResultPreview(next)
  }

  /* ── shared header ── */
  const renderHeader = (title: string, hasPrev: boolean, loading: boolean, onPrev: () => void, onNext: () => void): React.JSX.Element => (
    <div className={ styles.header }>
      <span className={ styles.title }>{ title }</span>
      <div className={ styles.buttonGroup }>
        <IconButton
          disabled={ !hasPrev || loading }
          icon={ { value: 'chevron-left' } }
          onClick={ onPrev }
          size="small"
          type="default"
        />
        <IconButton
          disabled={ loading }
          icon={ { value: 'chevron-right' } }
          onClick={ onNext }
          size="small"
          type="default"
        />
      </div>
    </div>
  )

  /* ── import mode render ── */
  if (props.mode === 'import') {
    const hasPrev = actualRecordIndex > 0
    const filteredRows = searchText.trim() === ''
      ? rows
      : rows.filter((r) =>
        r.label.toLowerCase().includes(searchText.toLowerCase()) ||
        r.value.toLowerCase().includes(searchText.toLowerCase())
      )

    return (
      <div className={ styles.wrapper }>
        { renderHeader(t('data-importer.mapping.advanced-modal.step-source.import-preview'), hasPrev, importLoading || isImportFetching, handleImportPrev, handleImportNext) }

        <Input
          onChange={ (e) => { setSearchText(e.target.value) } }
          placeholder={ t('data-importer.mapping.advanced-modal.step-source.search-placeholder') }
          prefix={ (
            <svg
              fill="none"
              height="14"
              viewBox="0 0 14 14"
              width="14"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M6.125 11.375C9.02246 11.375 11.375 9.02246 11.375 6.125C11.375 3.22754 9.02246 0.875 6.125 0.875C3.22754 0.875 0.875 3.22754 0.875 6.125C0.875 9.02246 3.22754 11.375 6.125 11.375Z"
                stroke={ token.colorTextTertiary }
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.5"
              />
              <path
                d="M12.25 12.25L10.0625 10.0625"
                stroke={ token.colorTextTertiary }
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.5"
              />
            </svg>
          ) }
          value={ searchText }
        />

        <div className={ styles.tableWrapper }>
          <div className={ styles.tableHeader }>
            <div className={ cx(styles.tableHeaderCell, styles.tableHeaderCellBorder) }>
              { t('data-importer.mapping.advanced-modal.step-source.column-name') }
            </div>
            <div className={ styles.tableHeaderCell }>
              { t('data-importer.mapping.advanced-modal.step-source.column-data') }
            </div>
          </div>

          { (importLoading || isImportFetching) && (
            <div className={ styles.stateMessage }>
              <Spin type="classic" />
            </div>
          ) }
          { !(importLoading || isImportFetching) && filteredRows.map((row, i) => {
            const isHighlighted = props.selectedDataSourceIndex.includes(row.dataIndex)
            const isLastRow = i === filteredRows.length - 1
            return (
              <div
                className={ cx(
                  styles.tableRow,
                  isHighlighted && styles.tableRowHighlighted,
                  !isLastRow && styles.tableRowBorder
                ) }
                key={ i }
              >
                <div className={ cx(styles.tableCell, styles.tableCellBorder) }>
                  { row.label }
                </div>
                <div className={ styles.tableCell }>
                  { row.value }
                </div>
              </div>
            )
          }) }
          { !(importLoading || isImportFetching) && filteredRows.length === 0 && (
            <div className={ styles.stateMessage }>{ t('data-importer.mapping.advanced-modal.no-data') }</div>
          ) }
        </div>
      </div>
    )
  }

  /* ── result mode render ── */
  const hasPrev = resultRecordNumber > 0

  return (
    <div className={ styles.wrapper }>
      { renderHeader(
        t('data-importer.mapping.advanced-modal.step-target.preview-result'),
        hasPrev,
        isResultLoading || isResultFetching,
        handleResultPrev,
        handleResultNext
      ) }

      <div className={ styles.previewArea }>
        { (isResultLoading || isResultFetching) && (
          <div className={ styles.muted }>
            <Spin type="classic" />
          </div>
        ) }
        { !(isResultLoading || isResultFetching) && previews.length === 0 && (
          <div className={ styles.muted }>{ t('data-importer.mapping.advanced-modal.no-preview') }</div>
        ) }
        { !(isResultLoading || isResultFetching) && previews.map((line, i) => (
          <div
            className={ styles.previewLine }
            key={ i }
          >
            { line }
          </div>
        )) }
      </div>
    </div>
  )
}
