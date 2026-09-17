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

import React, { useCallback, useEffect, useMemo, useRef, useState, useTransition } from 'react'
import {
  Button,
  Checkbox,
  Content,
  Flex,
  Form,
  Modal,
  Spin,
  useFormModal,
  useMessage
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { useTheme } from 'antd-style'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { type MappingConfigItem } from '../../../../types'
import { normalizeDataRow } from '../../../../utils/normalize-data-row'
import { usePreviewRecordQuery } from '../shared/use-preview-record-query'
import { useStyles } from './mapping-step.styles'
import { SourcesPanel } from './sources-panel/sources-panel'
import { MappingsPanel } from './mappings-panel/mappings-panel'
import { SourcePickerContent } from './source-picker-content/source-picker-content'
import { useMappingStepLoader } from './hooks/use-mapping-step-loader'
import { createMappingItem } from './utils/mapping-factory'
import { ensureMappingIdAtIndex } from './utils/mapping-identity'
import { PanelArrowIcon } from './panel-arrow-icon.inline'
import { MappingItemContextProvider } from './mapping-item-context'
import { type MappingSuggestion, computeAutofillSuggestions } from './utils/compute-autofill-suggestions'
import { AutofillSuggestionsPanel, applySelectedSuggestions } from './autofill-suggestions-panel'

function isMappingDebugEnabled (): boolean {
  return (globalThis as any).__DI_MAPPING_DEBUG__ === true
}

export interface MappingStepProps {
  configName: string
  isActive: boolean
}

export const MappingStep = React.memo(({ configName, isActive }: MappingStepProps): React.JSX.Element => {
  const { styles } = useStyles()
  const { t } = useTranslation()
  const theme = useTheme()
  const { validLanguages } = useSettings()
  const modal = useFormModal()
  const message = useMessage()
  const form = Form.useFormInstance()
  const loaderConfigType = Form.useWatch(['loaderConfig', 'type']) as string | undefined
  const interpreterConfigType = Form.useWatch(['interpreterConfig', 'type']) as string | undefined

  const {
    columnHeaderOptions,
    initialLoadDone,
    sourceRows,
    hasPreviewError,
    attributesMap,
    classId,
    getMappingConfig
  } = useMappingStepLoader(configName, isActive)

  const {
    dataPreview: autofillPreviewData,
    currentRecordIndex: autofillRecordIndex,
    isFetching: isAutofillPreviewFetching,
    load: loadAutofillPreviewRecord
  } = usePreviewRecordQuery({ configName, enabled: isActive })

  const [expandedKeys, setExpandedKeys] = useState<ReadonlySet<number> | 'all'>(new Set())
  const [expandAllPending, setExpandAllPending] = useState(false)
  const [, expandTransition] = useTransition()
  const [activeFilter, setActiveFilter] = useState<string | null>(null)
  const [suggestions, setSuggestions] = useState<MappingSuggestion[] | null>(null)
  const [selectedSuggestionIds, setSelectedSuggestionIds] = useState<Set<string>>(new Set())
  const autoExpandNewKeysRef = useRef(true)
  const prevLoaderTypeRef = useRef<string | undefined>(loaderConfigType)
  const prevInterpreterTypeRef = useRef<string | undefined>(interpreterConfigType)
  const bulkToggleTimingRef = useRef<{ action: 'expand-all' | 'collapse-all', startedAt: number } | null>(null)

  // Ref so callbacks that only *read* activeFilter at call time don't need to
  // capture it in their deps (which would make them new references on every filter change).
  const activeFilterRef = useRef(activeFilter)
  activeFilterRef.current = activeFilter

  const activeFilterLabel = useMemo(() => {
    if (activeFilter === null) return null
    return sourceRows.find((r) => r.dataIndex === activeFilter)?.label ?? activeFilter
  }, [activeFilter, sourceRows])

  const mappingItemContextValue = useMemo(
    () => ({ configName, classId, columnHeaderOptions, attributesMap, sourceRows }),
    [configName, classId, columnHeaderOptions, attributesMap, sourceRows]
  )

  useEffect(() => {
    const loaderChanged = prevLoaderTypeRef.current !== loaderConfigType
    const interpreterChanged = prevInterpreterTypeRef.current !== interpreterConfigType

    if (!loaderChanged && !interpreterChanged) return

    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] reset expanded panels on source type change', {
        prevLoaderType: prevLoaderTypeRef.current,
        nextLoaderType: loaderConfigType,
        prevInterpreterType: prevInterpreterTypeRef.current,
        nextInterpreterType: interpreterConfigType
      })
    }

    setExpandedKeys(new Set())
    prevLoaderTypeRef.current = loaderConfigType
    prevInterpreterTypeRef.current = interpreterConfigType
  }, [loaderConfigType, interpreterConfigType])

  const expandedKeysRef = useRef(expandedKeys)
  expandedKeysRef.current = expandedKeys

  const collapseAll = useCallback((visibleKeys: number[]): void => {
    const prev = expandedKeysRef.current
    const prevSet = prev === 'all' ? new Set(visibleKeys) : prev
    const allCollapsed = visibleKeys.every((k) => !prevSet.has(k))
    const action: 'expand-all' | 'collapse-all' = allCollapsed ? 'expand-all' : 'collapse-all'
    bulkToggleTimingRef.current = { action, startedAt: performance.now() }

    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] toggle expand/collapse all', {
        action,
        visibleCount: visibleKeys.length,
        previouslyExpandedVisibleCount: visibleKeys.filter((k) => prevSet.has(k)).length
      })
    }

    if (allCollapsed) {
      autoExpandNewKeysRef.current = true
      // Use startTransition so React can interleave mounting the heavy
      // MappingItemContent panels across frames, keeping the UI responsive.
      setExpandAllPending(true)
      expandTransition(() => {
        setExpandedKeys('all')
        setExpandAllPending(false)
      })
    } else {
      autoExpandNewKeysRef.current = false
      const next = new Set(prevSet)
      visibleKeys.forEach((k) => next.delete(k))
      setExpandedKeys(next)
    }
  }, [])

  useEffect(() => {
    if (!isMappingDebugEnabled()) return
    if (bulkToggleTimingRef.current === null) return

    if (bulkToggleTimingRef.current.action !== 'collapse-all') return

    const timing = bulkToggleTimingRef.current
    bulkToggleTimingRef.current = null

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        console.debug('[DI][Perf] expand/collapse all painted', {
          action: timing.action,
          durationMs: Number((performance.now() - timing.startedAt).toFixed(2))
        })
      })
    })
  }, [expandedKeys])

  const handleToggleKey = useCallback((key: number, allFieldKeys: number[]): void => {
    setExpandedKeys((prev) => {
      const prevSet = prev === 'all' ? new Set(allFieldKeys) : new Set(prev)
      if (prevSet.has(key)) {
        prevSet.delete(key)
      } else {
        prevSet.add(key)
      }
      return prevSet
    })
  }, [])

  const handleNewKey = useCallback((key: number): void => {
    setExpandedKeys((prev) => {
      if (!autoExpandNewKeysRef.current) {
        return prev
      }

      if (prev === 'all') return 'all'
      const next = new Set(prev)
      next.add(key)
      return next
    })
  }, [])

  const handleRemoveItem = useCallback((remove: (index: number) => void, index: number): void => {
    const debugEnabled = isMappingDebugEnabled()
    if (debugEnabled) {
      const beforeCount = getMappingConfig().length
      console.debug('[DI][Action] remove mapping requested', {
        index,
        beforeCount,
        activeFilter: activeFilterRef.current
      })
    }

    // Read the filter from the ref instead of a state updater: `remove()` mutates the form
    // store, and a state updater must stay pure — React invokes it twice in StrictMode,
    // which would delete two mappings for a single click.
    const currentFilter = activeFilterRef.current
    let clearedFilter: string | null = null

    if (currentFilter !== null) {
      const remaining = getMappingConfig().filter((_, i) => i !== index)
      const stillReferenced = remaining.some((item) =>
        (item.dataSourceIndex ?? []).includes(currentFilter)
      )
      if (!stillReferenced) {
        clearedFilter = currentFilter
        setActiveFilter(null)
      }
    }

    remove(index)

    if (debugEnabled) {
      console.debug('[DI][Action] remove mapping done', {
        index,
        clearedFilter,
        keptFilter: clearedFilter === null ? currentFilter : null
      })
    }
  }, [getMappingConfig])

  const createMappingItemFromSource = useCallback((dataIndex: string, label: string): MappingConfigItem => {
    return createMappingItem(dataIndex, label, 'manual')
  }, [])

  const handleAddItem = useCallback((add: (value?: MappingConfigItem, insertIndex?: number) => void, count: number): void => {
    const selectedRef: React.MutableRefObject<string | undefined> = { current: undefined }
    const errorRef: React.MutableRefObject<((show: boolean) => void) | undefined> = { current: undefined }

    modal.confirm({
      title: t('data-importer.mapping.new-modal.title'),
      content: (
        <SourcePickerContent
          errorRef={ errorRef }
          options={ columnHeaderOptions }
          valueRef={ selectedRef }
        />
      ),
      okText: t('data-importer.mapping.add'),
      onOk: async () => {
        const dataIndex = selectedRef.current
        if (dataIndex === undefined) {
          errorRef.current?.(true)
          return await Promise.reject(new Error('source required'))
        }
        const label = columnHeaderOptions.find((o) => o.value === dataIndex)?.label ?? dataIndex
        if (isMappingDebugEnabled()) {
          console.debug('[DI][Action] add mapping via modal', {
            dataIndex,
            label,
            insertIndex: 0,
            beforeCount: getMappingConfig().length
          })
        }

        add(createMappingItemFromSource(dataIndex, label), 0)

        const currentFilter = activeFilterRef.current
        if (currentFilter !== null && currentFilter !== dataIndex) {
          setActiveFilter(dataIndex)
        }
      }
    })
  }, [modal, t, columnHeaderOptions, createMappingItemFromSource, getMappingConfig])

  const handleInsertItem = useCallback((
    add: (value?: MappingConfigItem, insertIndex?: number) => void,
    insertIndex: number,
    dataIndex: string,
    label: string
  ): void => {
    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] insert mapping via drop', {
        dataIndex,
        label,
        insertIndex,
        beforeCount: getMappingConfig().length
      })
    }

    add(createMappingItemFromSource(dataIndex, label), insertIndex)
  }, [createMappingItemFromSource, getMappingConfig])

  const currentPreviewRow = useMemo((): Record<string, string | null> => {
    const rows = autofillPreviewData.length > 0
      ? autofillPreviewData.map(normalizeDataRow)
      : sourceRows
    return rows.reduce<Record<string, string | null>>((acc, r) => { acc[r.dataIndex] = r.value; return acc }, {})
  }, [autofillPreviewData, sourceRows])

  const handleOpenAutofillSuggestions = useCallback((): void => {
    if (columnHeaderOptions.length === 0) {
      void message.warning(t('data-importer.mapping.autofill-suggestions.no-source-columns'))
      return
    }

    const currentItems = getMappingConfig()
    const computed = computeAutofillSuggestions(
      columnHeaderOptions,
      attributesMap,
      currentItems,
      sourceRows,
      (validLanguages as string[] | undefined) ?? []
    )
    setSuggestions(computed)
    setSelectedSuggestionIds(new Set(computed.map((s) => s.id)))
    loadAutofillPreviewRecord(0)
  }, [getMappingConfig, columnHeaderOptions, attributesMap, sourceRows, validLanguages, message, t, loadAutofillPreviewRecord])

  const handlePrevPreviewRow = useCallback((): void => {
    const prev = Math.max(0, autofillRecordIndex - 1)
    if (prev !== autofillRecordIndex) loadAutofillPreviewRecord(prev)
  }, [autofillRecordIndex, loadAutofillPreviewRecord])

  const handleNextPreviewRow = useCallback((): void => {
    loadAutofillPreviewRecord(autofillRecordIndex + 1)
  }, [autofillRecordIndex, loadAutofillPreviewRecord])

  const handleToggleSuggestion = useCallback((id: string): void => {
    setSelectedSuggestionIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }, [])

  const handleCloseSuggestionsModal = useCallback((): void => {
    setSuggestions(null)
    setSelectedSuggestionIds(new Set())
  }, [])

  const handleSelectAllSuggestions = useCallback((): void => {
    if (suggestions === null) return
    setSelectedSuggestionIds((currentSelected) => {
      const allSelected = suggestions.every((s) => currentSelected.has(s.id))
      return allSelected ? new Set() : new Set(suggestions.map((s) => s.id))
    })
  }, [suggestions])

  const handleApplySuggestions = useCallback((): void => {
    if (suggestions === null) return
    const newItems = applySelectedSuggestions(suggestions, selectedSuggestionIds)
    const currentItems = getMappingConfig()

    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] autofill suggestions applied', {
        appliedCount: newItems.length,
        beforeCount: currentItems.length
      })
    }

    form.setFieldValue('mappingConfig', [...currentItems, ...newItems], { triggerChange: true })
    setSuggestions(null)
    setSelectedSuggestionIds(new Set())
  }, [suggestions, selectedSuggestionIds, getMappingConfig, form])

  const handleAddMappingFromSource = useCallback((dataIndex: string, label: string): void => {
    const currentItems = getMappingConfig()
    const newItem: MappingConfigItem = createMappingItemFromSource(dataIndex, label)
    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] add mapping from source panel', {
        dataIndex,
        label,
        beforeCount: currentItems.length
      })
    }
    form.setFieldValue('mappingConfig', [...currentItems, newItem], { triggerChange: true })
  }, [getMappingConfig, createMappingItemFromSource, form])

  const handleAddMappingForFilter = useCallback((add: (value?: MappingConfigItem, insertIndex?: number) => void): void => {
    const currentFilter = activeFilterRef.current
    if (currentFilter === null) return
    const label = activeFilterLabel ?? currentFilter
    if (isMappingDebugEnabled()) {
      console.debug('[DI][Action] add mapping for active filter', {
        dataIndex: currentFilter,
        label,
        beforeCount: getMappingConfig().length
      })
    }
    add(createMappingItemFromSource(currentFilter, label))
  }, [activeFilterLabel, createMappingItemFromSource, getMappingConfig])

  const getMappingIdByIndex = useCallback((index: number): string => {
    return ensureMappingIdAtIndex(form, index)
  }, [form])

  const modalFooter = useMemo(() => {
    if (suggestions !== null && suggestions.length === 0) {
      return [
        <Button
          key="ok"
          onClick={ handleCloseSuggestionsModal }
          type="primary"
        >
          { t('data-importer.mapping.autofill-suggestions.ok') }
        </Button>
      ]
    }

    const allSel = suggestions !== null && suggestions.length > 0 &&
      suggestions.every((s) => selectedSuggestionIds.has(s.id))
    const someSel = suggestions?.some((s) => selectedSuggestionIds.has(s.id)) === true

    return (
      <Flex style={ { width: '100%' } }>
        <Flex
          align="center"
          gap="small"
          style={ { flex: 1 } }
        >
          <Checkbox
            checked={ allSel }
            indeterminate={ someSel && !allSel }
            onChange={ handleSelectAllSuggestions }
          />
          <span>
            { t('data-importer.mapping.autofill-suggestions.selected', {
              count: selectedSuggestionIds.size
            }) }
          </span>
        </Flex>
        { isAutofillPreviewFetching && (
          <Spin
            size="small"
            style={ { marginInlineEnd: 8 } }
            type="classic"
          />
        ) }
        <Flex
          align="center"
          gap="small"
        >
          <Button
            onClick={ handleCloseSuggestionsModal }
            type="default"
          >
            { t('data-importer.mapping.autofill-suggestions.reject-all') }
          </Button>
          <Button
            disabled={ selectedSuggestionIds.size === 0 }
            onClick={ handleApplySuggestions }
            type="primary"
          >
            { t('data-importer.mapping.autofill-suggestions.apply') }
          </Button>
        </Flex>
      </Flex>
    )
  }, [t, suggestions, selectedSuggestionIds, isAutofillPreviewFetching, handleSelectAllSuggestions, handleCloseSuggestionsModal, handleApplySuggestions])

  return (
    <Content loading={ !initialLoadDone }>
      <MappingItemContextProvider value={ mappingItemContextValue }>
        <Flex className={ styles.mappingLayout }>
          <div className={ styles.mappingLayoutLeft }>
            <SourcesPanel
              activeFilter={ activeFilter }
              configName={ configName }
              hasPreviewError={ hasPreviewError }
              onAddMappingFromSource={ handleAddMappingFromSource }
              onSetFilter={ setActiveFilter }
              sourceRows={ sourceRows }
            />
          </div>

          <Flex
            align="center"
            className={ styles.mappingLayoutCenter }
            vertical
          >
            <Flex
              align="center"
              className={ styles.mappingLayoutCenterArrow }
              justify="center"
            >
              <PanelArrowIcon />
            </Flex>
          </Flex>

          <div className={ styles.mappingLayoutRight }>
            <FieldWidthProvider fieldWidthValues={ { small: 9999, medium: 9999, large: 9999 } }>
              <MappingsPanel
                activeFilter={ activeFilter }
                activeFilterLabel={ activeFilterLabel }
                expandAllPending={ expandAllPending }
                expandedKeys={ expandedKeys }
                getMappingIdByIndex={ getMappingIdByIndex }
                onAddItem={ handleAddItem }
                onAddMappingForFilter={ handleAddMappingForFilter }
                onCollapseAll={ collapseAll }
                onInsertItem={ handleInsertItem }
                onNewKey={ handleNewKey }
                onOpenAutofillSuggestions={ handleOpenAutofillSuggestions }
                onRemoveItem={ handleRemoveItem }
                onToggleKey={ handleToggleKey }
              />
            </FieldWidthProvider>
          </div>
        </Flex>
      </MappingItemContextProvider>

      <Modal
        footer={ modalFooter }
        onCancel={ handleCloseSuggestionsModal }
        open={ suggestions !== null }
        styles={ { body: { padding: 0, paddingBlock: 0, paddingInline: 0, maxHeight: '60vh', overflowY: 'auto' }, footer: { padding: '12px 16px', borderTop: suggestions !== null && suggestions.length > 0 ? `1px solid ${theme.colorPrimaryBorder}` : 'none' } } }
        title={ t('data-importer.mapping.autofill-suggestions.title') }
        width={ 1100 }
      >
        { suggestions !== null && (
          <AutofillSuggestionsPanel
            hasPrevRow={ autofillRecordIndex > 0 }
            isLoadingPreviewRow={ isAutofillPreviewFetching }
            onNextRow={ handleNextPreviewRow }
            onPrevRow={ handlePrevPreviewRow }
            onToggle={ handleToggleSuggestion }
            previewRow={ currentPreviewRow }
            selectedIds={ selectedSuggestionIds }
            suggestions={ suggestions }
          />
        ) }
      </Modal>
    </Content>
  )
})

MappingStep.displayName = 'MappingStep'
