/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */

import React, { useEffect, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { IconButton, SearchInput, Flex, Space, Spin } from '@pimcore/studio-ui-bundle/components'
import {
  useBundleDataImporterConfigLoadTransformationResultQuery
} from '../../../../../data-importer-api-slice.gen'
import { normalizeDataRow, type DataRow } from '../../../../../utils/normalize-data-row'
import { type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig, type MappingConfigItem } from '../../../../../types'
import { useStyles } from './preview-panel.styles'
import { usePreviewRecordQuery } from '../../shared/use-preview-record-query'

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

export const PreviewPanel = (props: PreviewPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles, cx } = useStyles()

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
    resultRequest!,
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

  const fetchResultPreview = (record: number): void => {
    const currentConfig: Record<string, object> | undefined = props.currentMappingItem === undefined
      ? undefined
      : {
          mappingConfig: [props.currentMappingItem] as object[],
          ...(props.baseConfig?.loaderConfig !== undefined && { loaderConfig: props.baseConfig.loaderConfig as object }),
          ...(props.baseConfig?.interpreterConfig !== undefined && { interpreterConfig: props.baseConfig.interpreterConfig as object }),
          ...(props.baseConfig?.resolverConfig !== undefined && { resolverConfig: props.baseConfig.resolverConfig as object }),
          ...(props.baseConfig?.processingConfig !== undefined && { processingConfig: props.baseConfig.processingConfig as object })
        }

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
    setPreviews(props.currentMappingItem === undefined ? rawPreviews : rawPreviews.slice(0, 1))
  }, [props.mode, resultPreviewResponse, props.currentMappingItem])

  useEffect(() => {
    if (!isResultError) return
    setPreviews([])
  }, [isResultError])

  useEffect(() => {
    if (props.mode !== 'result') return
    fetchResultPreview(resultRecordNumber)
  }, [props.refreshToken])

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

  const renderHeader = (title: string, hasPrev: boolean, loading: boolean, onPrev: () => void, onNext: () => void): React.JSX.Element => (
    <Flex
      align="center"
      className={ styles.header }
      justify="space-between"
    >
      <span className={ styles.title }>{ title }</span>
      <Space size="mini">
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
      </Space>
    </Flex>
  )

  if (props.mode === 'import') {
    const hasPrev = actualRecordIndex > 0
    const filteredRows = searchText.trim() === ''
      ? rows
      : rows.filter((r) =>
        r.label.toLowerCase().includes(searchText.toLowerCase()) ||
        r.value.toLowerCase().includes(searchText.toLowerCase())
      )

    return (
      <Flex
        className={ styles.wrapper }
        gap="extra-small"
        vertical
      >
        { renderHeader(t('data-importer.mapping.advanced-modal.step-source.import-preview'), hasPrev, importLoading || isImportFetching, handleImportPrev, handleImportNext) }

        <SearchInput
          maxWidth={ '100%' }
          onChange={ (e) => { setSearchText(e.target.value) } }
          placeholder={ t('data-importer.mapping.advanced-modal.step-source.search-placeholder') + 'asdf' }
          value={ searchText }
          withClear
          withPrefix
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
                key={ row.dataIndex }
              >
                <Flex
                  align="center"
                  className={ cx(styles.tableCell, styles.tableCellBorder) }
                >
                  { row.label }
                </Flex>
                <Flex
                  align="center"
                  className={ styles.tableCell }
                >
                  { row.value }
                </Flex>
              </div>
            )
          }) }
          { !(importLoading || isImportFetching) && filteredRows.length === 0 && (
            <div className={ styles.stateMessage }>{ t('data-importer.mapping.advanced-modal.no-data') }</div>
          ) }
        </div>
      </Flex>
    )
  }

  const hasPrev = resultRecordNumber > 0

  return (
    <Flex
      className={ styles.wrapper }
      gap="extra-small"
      vertical
    >
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
            key={ `preview-${i}` }
          >
            { line }
          </div>
        )) }
      </div>
    </Flex>
  )
}
