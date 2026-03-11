/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react'
import {
  Divider,
  Droppable,
  type DragAndDropInfo,
  Form,
  IconTextButton,
  Text
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingConfigItem, type ClassAttribute } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingItemWithFilter, MappingDropZone } from '../mapping-item/mapping-item'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'
import { isDropAllowed } from '../utils/mapping-drop-policy'

// ── FilteredEmptyState ─────────────────────────────────────────────────────
// Reads the live form to check whether any mapping items reference the active
// filter. If none do, shows a "no mappings" message + add button.
// Must be a component so it can use Form.useWatch (hooks rule).

interface FilteredEmptyStateProps {
  activeFilter: string
  activeFilterLabel: string | null
  fields: Array<{ name: number, key: number }>
  onAddMappingForFilter: () => void
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
}

const FilteredEmptyState = ({
  activeFilter,
  activeFilterLabel,
  fields,
  onAddMappingForFilter,
  add,
  onInsertItem,
  onDropped
}: FilteredEmptyStateProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const allItems = Form.useWatch('mappingConfig') as MappingConfigItem[] | undefined ?? []

  const handleDrop = useCallback((info: DragAndDropInfo): void => {
    const { dataIndex, label } = info.data as { dataIndex: string, label: string }
    if (!isDropAllowed(dataIndex, activeFilter)) return

    onInsertItem(add, fields.length, dataIndex, label)
    onDropped(fields.length)
  }, [activeFilter, onInsertItem, add, fields.length, onDropped])

  const hasMatch = allItems.some((item) =>
    (item.dataSourceIndex ?? []).includes(activeFilter)
  )

  if (hasMatch) return null

  return (
    <Droppable
      className={ styles.filterEmptyState }
      disableDndActiveIndicator={ false }
      isValidContext={ (info) => info.type === DND_TYPE && isDropAllowed((info.data as { dataIndex?: string }).dataIndex, activeFilter) }
      onDrop={ handleDrop }
      variant="default"
    >
      <DndClassDiv className={ styles.filterEmptyStateInner }>
        <span>
          { t('data-importer.mapping.filter-empty', {
            source: activeFilterLabel ?? activeFilter
          }) }
        </span>
        <IconTextButton
          icon={ { value: 'add' } }
          onClick={ onAddMappingForFilter }
          type="default"
        >
          { t('data-importer.mapping.add') }
        </IconTextButton>
      </DndClassDiv>
    </Droppable>
  )
}

// ── EmptyDropZone ──────────────────────────────────────────────────────────
// Full-height droppable shown when there are no mapping items.
// Accepts source column drops exactly like MappingDropZone (insertIndex 0).

interface EmptyDropZoneProps {
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
}

const EmptyDropZone = ({ add, onInsertItem, onDropped }: EmptyDropZoneProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const handleDrop = useCallback((info: DragAndDropInfo): void => {
    const { dataIndex, label } = info.data as { dataIndex: string, label: string }
    onInsertItem(add, 0, dataIndex, label)
    onDropped(0)
  }, [add, onInsertItem, onDropped])

  return (
    <Droppable
      className={ styles.emptyState }
      disableDndActiveIndicator={ false }
      isValidContext={ (info) => info.type === DND_TYPE }
      onDrop={ handleDrop }
      variant="default"
    >
      <DndClassDiv className={ styles.emptyStateInner }>
        <span>{ t('data-importer.mapping.empty-state') }</span>
      </DndClassDiv>
    </Droppable>
  )
}

// ── MappingsPanelContent ───────────────────────────────────────────────────
// Extracted as a memoized component so that Form.List's render-prop does not
// re-render the entire item list on every parent state change.
// Receives `add` / `remove` from Form.List which are stable references.

interface MappingsPanelContentProps {
  fields: Array<{ name: number, key: number }>
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  remove: (index: number) => void
  hasItems: boolean
  flashIndex: number | null
  classId: string | undefined
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
  activeFilter: string | null
  activeFilterLabel: string | null
  /** Set of field.key values that are currently expanded, or 'all' if everything is expanded */
  expandedKeys: ReadonlySet<number> | 'all'
  /** Whether all visible items are collapsed (none in expandedKeys) */
  allVisibleCollapsed: boolean
  attributesMap: Record<string, ClassAttribute[]>
  onCollapseAll: (visibleKeys: number[]) => void
  onNewKey: (key: number) => void
  onToggleKey: (key: number, allFieldKeys: number[]) => void
  onAutoFill: (fields: Array<{ name: number }>, add: (value?: MappingConfigItem, insertIndex?: number) => void) => void
  onAddItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, count: number) => void
  onRemoveItem: (remove: (index: number) => void, index: number) => void
  onAddMappingForFilter: (add: (value?: MappingConfigItem, insertIndex?: number) => void) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
  getMappingIdByIndex: (index: number) => string
}

const MappingsPanelContent = React.memo(({
  fields,
  add,
  remove,
  hasItems,
  flashIndex,
  classId,
  configName,
  columnHeaderOptions,
  activeFilter,
  activeFilterLabel,
  expandedKeys,
  allVisibleCollapsed,
  attributesMap,
  onCollapseAll,
  onNewKey,
  onToggleKey,
  onAutoFill,
  onAddItem,
  onRemoveItem,
  onAddMappingForFilter,
  onInsertItem,
  onDropped,
  getMappingIdByIndex
}: MappingsPanelContentProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  // Detect newly added keys and notify parent after render.
  const prevFieldKeysRef = useRef<Set<number>>(new Set(fields.map((f) => f.key)))
  useLayoutEffect(() => {
    const currentKeySet = new Set(fields.map((f) => f.key))
    currentKeySet.forEach((key) => {
      if (!prevFieldKeysRef.current.has(key)) {
        onNewKey(key)
      }
    })
    prevFieldKeysRef.current = currentKeySet
  }, [fields, onNewKey])

  const newlyAddedKeys = useMemo(() => {
    const previousKeys = prevFieldKeysRef.current
    return new Set(
      fields
        .map((f) => f.key)
        .filter((key) => !previousKeys.has(key))
    )
  }, [fields])

  // Compute which field keys are visible under the current filter
  const visibleKeys = useMemo(
    () => fields
      .filter((f) => {
        if (activeFilter === null) return true
        // We don't have form values here, but the hidden check is done inside MappingItemWithFilter.
        // For the purpose of collapse/expand we include all keys when no filter is active,
        // and rely on the parent-passed allVisibleCollapsed which is computed there.
        return true
      })
      .map((f) => f.key),
    [fields, activeFilter]
  )

  // Stable per-item remove callback factory — memoized so identity only changes
  // when `remove` itself changes (which Form.List guarantees is stable).
  const makeOnRemoveItem = useCallback(
    (index: number) => () => { onRemoveItem(remove, index) },
    [onRemoveItem, remove]
  )

  // Memoize the item list. Each item is keyed by field.key so React only
  // re-renders items whose expanded/data state actually changed.
  const itemList = useMemo(() => {
    const allFieldKeys = fields.map((f) => f.key)
    const acceptedDataIndex = activeFilter ?? undefined

    return fields.map((field, arrayIndex) => {
      const mappingId = getMappingIdByIndex(field.name)
      const isExpanded = expandedKeys === 'all' || expandedKeys.has(field.key) || newlyAddedKeys.has(field.key)

      return (
        <React.Fragment key={ field.key }>
          <MappingItemWithFilter
            acceptedDataIndex={ acceptedDataIndex }
            activeFilter={ activeFilter }
            add={ add }
            attributesMap={ attributesMap }
            classId={ classId }
            columnHeaderOptions={ columnHeaderOptions }
            configName={ configName }
            expanded={ isExpanded }
            fieldIndex={ field.name }
            insertIndex={ arrayIndex }
            isNew={ flashIndex === arrayIndex }
            mappingId={ mappingId }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
            onRemoveItem={ makeOnRemoveItem(field.name) }
            onToggle={ () => { onToggleKey(field.key, allFieldKeys) } }
            remove={ remove }
          />
        </React.Fragment>
      )
    })
  }, [fields, add, remove, activeFilter, attributesMap, classId, expandedKeys, newlyAddedKeys,
    columnHeaderOptions, configName, flashIndex, onDropped,
    onInsertItem, makeOnRemoveItem, onToggleKey, getMappingIdByIndex])

  return (
    <>
      {/* Header */}
      <div className={ styles.mappingsHeader }>
        <Text className={ styles.mappingsTitle }>
          { t('data-importer.mapping.title-short') }
        </Text>

        <div className={ styles.mappingsActions }>
          <IconTextButton
            icon={ { value: 'new' } }
            onClick={ () => { onAddItem(add, fields.length) } }
            type="default"
          >
            { t('data-importer.mapping.add') }
          </IconTextButton>

          <Divider
            className={ styles.mappingsDivider }
            type="vertical"
          />

          <IconTextButton
            icon={ { value: 'autofill' } }
            onClick={ () => { onAutoFill(fields, add) } }
            type="default"
          >
            { t('data-importer.mapping.auto-fill') }
          </IconTextButton>

          { hasItems && (
            <span
              className={ styles.collapseAllLink }
              onClick={ () => { onCollapseAll(visibleKeys) } }
            >
              { allVisibleCollapsed
                ? t('data-importer.mapping.expand-all')
                : t('data-importer.mapping.collapse-all') }
            </span>
          ) }
        </div>
      </div>

      {/* Mapping items */}
      <div className={ styles.mappingsContent }>
        { !hasItems && activeFilter === null && (
          <EmptyDropZone
            add={ add }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
          />
        ) }

        { itemList }

        {/* Drop zone after the last item */}
        { hasItems && (
          <MappingDropZone
            acceptedDataIndex={ activeFilter ?? undefined }
            add={ add }
            insertIndex={ fields.length }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
          />
        ) }

        { activeFilter !== null && (
          <FilteredEmptyState
            activeFilter={ activeFilter }
            activeFilterLabel={ activeFilterLabel }
            add={ add }
            fields={ fields }
            onAddMappingForFilter={ () => { onAddMappingForFilter(add) } }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
          />
        ) }
      </div>
    </>
  )
})

MappingsPanelContent.displayName = 'MappingsPanelContent'

// ── MappingsPanel ──────────────────────────────────────────────────────────

export interface MappingsPanelProps {
  classId: string | undefined
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
  activeFilter: string | null
  activeFilterLabel: string | null
  /** Set of field.key values that are currently expanded, or 'all' if everything is expanded */
  expandedKeys: ReadonlySet<number> | 'all'
  /** Pre-loaded class attributes keyed by transformationResultType */
  attributesMap: Record<string, ClassAttribute[]>
  onCollapseAll: (visibleKeys: number[]) => void
  onNewKey: (key: number) => void
  onToggleKey: (key: number, allFieldKeys: number[]) => void
  onAutoFill: (fields: Array<{ name: number }>, add: (value?: MappingConfigItem, insertIndex?: number) => void) => void
  onAddItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, count: number) => void
  onRemoveItem: (remove: (index: number) => void, index: number) => void
  onAddMappingForFilter: (add: (value?: MappingConfigItem, insertIndex?: number) => void) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  getMappingIdByIndex: (index: number) => string
}

export const MappingsPanel = ({
  classId,
  configName,
  columnHeaderOptions,
  activeFilter,
  activeFilterLabel,
  expandedKeys,
  attributesMap,
  onCollapseAll,
  onNewKey,
  onToggleKey,
  onAutoFill,
  onAddItem,
  onRemoveItem,
  onAddMappingForFilter,
  onInsertItem,
  getMappingIdByIndex
}: MappingsPanelProps): React.JSX.Element => {
  const { styles } = useStyles()

  // Track which array index was most recently inserted via drop, to flash it.
  const [flashIndex, setFlashIndex] = useState<number | null>(null)
  const flashTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const handleDropped = useCallback((insertIndex: number): void => {
    if (flashTimerRef.current !== null) clearTimeout(flashTimerRef.current)
    setFlashIndex(insertIndex)
    flashTimerRef.current = setTimeout(() => { setFlashIndex(null) }, 400)
  }, [])

  return (
    <div className={ styles.panel }>
      <Form.List name="mappingConfig">
        { (fields, { add, remove }) => {
          const hasItems = fields.length > 0
          // allVisibleCollapsed: true only when expandedKeys is an explicit Set and none
          // of the fields are in it. When expandedKeys === 'all', nothing is collapsed.
          const allVisibleCollapsed = expandedKeys !== 'all' && fields.every((f) => !expandedKeys.has(f.key))

          return (
            <MappingsPanelContent
              activeFilter={ activeFilter }
              activeFilterLabel={ activeFilterLabel }
              add={ add }
              allVisibleCollapsed={ allVisibleCollapsed }
              attributesMap={ attributesMap }
              classId={ classId }
              columnHeaderOptions={ columnHeaderOptions }
              configName={ configName }
              expandedKeys={ expandedKeys }
              fields={ fields }
              flashIndex={ flashIndex }
              getMappingIdByIndex={ getMappingIdByIndex }
              hasItems={ hasItems }
              onAddItem={ onAddItem }
              onAddMappingForFilter={ onAddMappingForFilter }
              onAutoFill={ onAutoFill }
              onCollapseAll={ onCollapseAll }
              onDropped={ handleDropped }
              onInsertItem={ onInsertItem }
              onNewKey={ onNewKey }
              onRemoveItem={ onRemoveItem }
              onToggleKey={ onToggleKey }
              remove={ remove }
            />
          )
        } }
      </Form.List>
    </div>
  )
}
