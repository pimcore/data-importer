/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo, useState } from 'react'
import {
  Content,
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

export interface MappingStepProps {
  configName: string
  isActive: boolean
}

export const MappingStep = ({ configName, isActive }: MappingStepProps): React.JSX.Element => {
  const { styles } = useStyles()
  const { t } = useTranslation()
  const modal = useFormModal()
  const form = Form.useFormInstance()

  const {
    columnHeaderOptions,
    initialLoadDone,
    sourceRows,
    hasPreviewError,
    attributesMap,
    classId,
    getMappingConfig
  } = useMappingStepLoader(configName, isActive)

  // expandedKeys: explicit Set of field.key values that are expanded.
  // Empty set on init = all collapsed. New items add their key on creation.
  const [expandedKeys, setExpandedKeys] = useState<ReadonlySet<number> | 'all'>(new Set())
  const [activeFilter, setActiveFilter] = useState<string | null>(null)

  // Derive active filter label from sourceRows for display in empty-state message
  const activeFilterLabel = useMemo(() => {
    if (activeFilter === null) return null
    return sourceRows.find((r) => r.dataIndex === activeFilter)?.label ?? activeFilter
  }, [activeFilter, sourceRows])

  const collapseAll = (visibleKeys: number[]): void => {
    setExpandedKeys((prev) => {
      const prevSet = prev === 'all' ? new Set(visibleKeys) : prev
      // Toggle: if ALL visible keys are already collapsed, expand them; otherwise collapse them all
      const allCollapsed = visibleKeys.every((k) => !prevSet.has(k))
      if (allCollapsed) {
        const next = new Set(prevSet)
        visibleKeys.forEach((k) => next.add(k))
        return next
      } else {
        const next = new Set(prevSet)
        visibleKeys.forEach((k) => next.delete(k))
        return next
      }
    })
  }

  // Called when the user clicks a single panel header to toggle collapse.
  // allFieldKeys is the current full list of field keys from Form.List,
  // needed to transition from the 'all' sentinel to an explicit Set.
  const handleToggleKey = (key: number, allFieldKeys: number[]): void => {
    setExpandedKeys((prev) => {
      const prevSet = prev === 'all' ? new Set(allFieldKeys) : new Set(prev)
      if (prevSet.has(key)) {
        prevSet.delete(key)
      } else {
        prevSet.add(key)
      }
      return prevSet
    })
  }

  const handleNewKey = (key: number): void => {
    setExpandedKeys((prev) => {
      if (prev === 'all') return 'all' // already all expanded, new item is expanded too
      const next = new Set(prev)
      next.add(key)
      return next
    })
  }

  const handleRemoveItem = (remove: (index: number) => void, index: number): void => {
    // Before removing, check if the active filter would become empty after deletion
    if (activeFilter !== null) {
      const currentItems = getMappingConfig()
      // Items remaining after this removal
      const remaining = currentItems.filter((_, i) => i !== index)
      const stillReferenced = remaining.some((item) =>
        (item.dataSourceIndex ?? []).includes(activeFilter)
      )
      if (!stillReferenced) {
        setActiveFilter(null)
      }
    }
    remove(index)
  }

  const handleAddItem = (add: (value?: MappingConfigItem, insertIndex?: number) => void, count: number): void => {
    // A ref shared between this closure and the modal content component.
    // The component writes into it on every Select change; we read it in onOk.
    const selectedRef: React.MutableRefObject<string | undefined> = { current: undefined }
    // The errorRef lets us trigger error display inside SourcePickerContent from onOk
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
          // Returning a rejected promise keeps the modal open
          return await Promise.reject(new Error('source required'))
        }
        const label = columnHeaderOptions.find((o) => o.value === dataIndex)?.label ?? dataIndex
        add(createMappingItemFromSource(dataIndex, label), 0)

        if (activeFilter !== null && activeFilter !== dataIndex) {
          setActiveFilter(dataIndex)
        }
      }
    })
  }

  const createMappingItemFromSource = (dataIndex: string, label: string): MappingConfigItem => {
    return createMappingItem(dataIndex, label, 'manual')
  }

  const handleInsertItem = (
    add: (value?: MappingConfigItem, insertIndex?: number) => void,
    insertIndex: number,
    dataIndex: string,
    label: string
  ): void => {
    add(createMappingItemFromSource(dataIndex, label), insertIndex)
  }

  const handleAutoFill = (
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
  }

  const handleAddMappingFromSource = (dataIndex: string, label: string): void => {
    const currentItems = getMappingConfig()

    const newItem: MappingConfigItem = createMappingItemFromSource(dataIndex, label)

    form.setFieldValue('mappingConfig', [...currentItems, newItem], { triggerChange: true })
  }

  // Called from FilteredEmptyState "Add" button: creates a mapping pre-filled with the active filter source
  const handleAddMappingForFilter = (add: (value?: MappingConfigItem, insertIndex?: number) => void): void => {
    if (activeFilter === null) return
    const label = activeFilterLabel ?? activeFilter
    add(createMappingItemFromSource(activeFilter, label))
  }

  const getMappingIdByIndex = (index: number): string => {
    return ensureMappingIdAtIndex(form, index)
  }

  return (
    <Content loading={ !initialLoadDone }>
      <div className={ styles.mappingLayout }>
        {/* Left: sources panel — fixed 450px */}
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

        {/* Center: 46px gap column with sticky arrow */}
        <div className={ styles.mappingLayoutCenter }>
          <div className={ styles.mappingLayoutCenterArrow }>
            <svg
              fill="none"
              height="38"
              viewBox="0 0 38 38"
              width="38"
              xmlns="http://www.w3.org/2000/svg"
            >
              <g clipPath="url(#panel-arrow-clip)">
                <path
                  d="M26.9167 12.6641L33.25 18.9974L26.9167 25.3307"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                />
                <path
                  d="M33.25 19L24.7095 19C22.9533 19.0001 21.2242 18.5666 19.6758 17.738C18.1274 16.9094 16.8075 15.7113 15.8333 14.25C14.8592 12.7887 13.5393 11.5906 11.9909 10.762C10.4424 9.93337 8.71337 9.49988 6.95717 9.5L4.75 9.5"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                />
                <path
                  d="M33.25 19L24.7095 19C22.9533 18.9999 21.2242 19.4334 19.6758 20.262C18.1274 21.0906 16.8075 22.2887 15.8333 23.75C14.8592 25.2113 13.5393 26.4094 11.9909 27.238C10.4424 28.0666 8.71337 28.5001 6.95717 28.5L4.75 28.5"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                />
              </g>
              <defs>
                <clipPath id="panel-arrow-clip">
                  <rect
                    fill="white"
                    height="38"
                    transform="translate(38 1.66103e-06) rotate(90)"
                    width="38"
                  />
                </clipPath>
              </defs>
            </svg>
          </div>
        </div>

        {/* Right: mappings panel — fills remaining space */}
        <div className={ styles.mappingLayoutRight }>
          <FieldWidthProvider fieldWidthValues={ { small: 9999, medium: 9999, large: 9999 } }>
            <MappingsPanel
              activeFilter={ activeFilter }
              activeFilterLabel={ activeFilterLabel }
              attributesMap={ attributesMap }
              classId={ classId }
              columnHeaderOptions={ columnHeaderOptions }
              configName={ configName }
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
      </div>
    </Content>
  )
}
