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
  Content,
  Flex,
  Form,
  useFormModal
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { type MappingConfigItem } from '../../../../types'
import { useStyles } from './mapping-step.styles'
import { SourcesPanel } from './sources-panel/sources-panel'
import { MappingsPanel } from './mappings-panel/mappings-panel'
import { SourcePickerContent } from './source-picker-content/source-picker-content'
import { useMappingStepLoader } from './hooks/use-mapping-step-loader'
import { createMappingItem } from './utils/mapping-factory'
import { ensureMappingIdAtIndex } from './utils/mapping-identity'
import { PanelArrowIcon } from './panel-arrow-icon.inline'
import { MappingItemContextProvider } from './mapping-item-context'

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
  const modal = useFormModal()
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

  const [expandedKeys, setExpandedKeys] = useState<ReadonlySet<number> | 'all'>(new Set())
  const [expandAllPending, setExpandAllPending] = useState(false)
  const [, expandTransition] = useTransition()
  const [activeFilter, setActiveFilter] = useState<string | null>(null)
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
    () => ({ configName, classId, columnHeaderOptions, attributesMap }),
    [configName, classId, columnHeaderOptions, attributesMap]
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

    setActiveFilter((currentFilter) => {
      if (currentFilter !== null) {
        const currentItems = getMappingConfig()
        const remaining = currentItems.filter((_, i) => i !== index)
        const stillReferenced = remaining.some((item) =>
          (item.dataSourceIndex ?? []).includes(currentFilter)
        )
        if (!stillReferenced) {
          remove(index)
          if (debugEnabled) {
            console.debug('[DI][Action] remove mapping + clear filter', {
              index,
              clearedFilter: currentFilter
            })
          }
          return null
        }
      }
      remove(index)
      if (debugEnabled) {
        console.debug('[DI][Action] remove mapping done', {
          index,
          keptFilter: currentFilter
        })
      }
      return currentFilter
    })
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

  const handleAutoFill = useCallback((
    fields: Array<{ name: number }>,
    add: (defaultValue?: MappingConfigItem, insertIndex?: number) => void
  ): void => {
    const currentItems = getMappingConfig()

    const usedIndices = new Set<string>()
    currentItems.forEach((item) => {
      ;(item.dataSourceIndex ?? []).forEach((idx) => usedIndices.add(idx))
    })

    columnHeaderOptions.forEach((opt) => {
      if (!usedIndices.has(opt.value)) {
        add({
          ...createMappingItem(opt.value, opt.label, 'autofill')
        })
      }
    })

    if (isMappingDebugEnabled()) {
      const addedCount = columnHeaderOptions.filter((opt) => !usedIndices.has(opt.value)).length
      console.debug('[DI][Action] auto-fill mappings', {
        addedCount,
        beforeCount: currentItems.length,
        sourceCount: columnHeaderOptions.length
      })
    }
  }, [getMappingConfig, columnHeaderOptions])

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
                onAutoFill={ handleAutoFill }
                onCollapseAll={ collapseAll }
                onInsertItem={ handleInsertItem }
                onNewKey={ handleNewKey }
                onRemoveItem={ handleRemoveItem }
                onToggleKey={ handleToggleKey }
              />
            </FieldWidthProvider>
          </div>
        </Flex>
      </MappingItemContextProvider>
    </Content>
  )
})

MappingStep.displayName = 'MappingStep'
