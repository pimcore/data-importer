/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useLayoutEffect, useMemo, useRef } from 'react'
import { Button, Divider, Flex, IconTextButton, Text } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingDropZone, MappingItemWithFilter } from '../mapping-item'
import { EmptyDropZone } from './empty-drop-zone'
import { FilteredEmptyState } from './filtered-empty-state'

function isMappingDebugEnabled (): boolean {
  return (globalThis as any).__DI_MAPPING_DEBUG__ === true
}

export interface MappingsPanelContentProps {
  fields: Array<{ name: number, key: number }>
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  remove: (index: number) => void
  hasItems: boolean
  flashIndex: number | null
  expandedKeys: ReadonlySet<number> | 'all'
  allVisibleCollapsed: boolean
  activeFilter: string | null
  activeFilterLabel: string | null
  onCollapseAll: (visibleKeys: number[]) => void
  onNewKey: (key: number) => void
  onToggleKey: (key: number, allFieldKeys: number[]) => void
  onOpenAutofillSuggestions: () => void
  onAddItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, count: number) => void
  onRemoveItem: (remove: (index: number) => void, index: number) => void
  onAddMappingForFilter: (add: (value?: MappingConfigItem, insertIndex?: number) => void) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
  getMappingIdByIndex: (index: number) => string
}

export const MappingsPanelContent = React.memo(({
  fields,
  add,
  remove,
  hasItems,
  flashIndex,
  activeFilter,
  activeFilterLabel,
  expandedKeys,
  allVisibleCollapsed,
  onCollapseAll,
  onNewKey,
  onToggleKey,
  onOpenAutofillSuggestions,
  onAddItem,
  onRemoveItem,
  onAddMappingForFilter,
  onInsertItem,
  onDropped,
  getMappingIdByIndex
}: MappingsPanelContentProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const renderCountRef = useRef(0)

  renderCountRef.current += 1
  if (isMappingDebugEnabled() && (renderCountRef.current === 1 || renderCountRef.current % 20 === 0)) {
    console.debug('[DI][MappingsPanelContent] render', {
      renderCount: renderCountRef.current,
      fieldCount: fields.length,
      activeFilter,
      flashIndex,
      expandedMode: expandedKeys === 'all' ? 'all' : 'set'
    })
  }

  const prevFieldKeysRef = useRef<Set<number>>(new Set(fields.map((f) => f.key)))
  useLayoutEffect(() => {
    const debugEnabled = isMappingDebugEnabled()
    const currentKeySet = new Set(fields.map((f) => f.key))
    const previousKeySet = prevFieldKeysRef.current
    const addedKeys = Array.from(currentKeySet).filter((key) => !previousKeySet.has(key))

    // Only auto-expand when exactly one item was added (regular add/insert flows).
    // On hydration/reload or bulk operations (multiple new keys), skip auto-expand
    // to avoid opening many heavy panels at once.
    if (addedKeys.length === 1) {
      onNewKey(addedKeys[0])
    } else if (debugEnabled && addedKeys.length > 1) {
      console.debug('[DI][MappingsPanelContent] skip auto-expand for bulk add', {
        addedKeyCount: addedKeys.length,
        fieldCount: fields.length
      })
    }

    prevFieldKeysRef.current = currentKeySet
  }, [fields, onNewKey])

  const visibleKeys = useMemo(
    () => fields.map((f) => f.key),
    [fields]
  )

  const makeOnRemoveItem = useCallback(
    (index: number) => () => { onRemoveItem(remove, index) },
    [onRemoveItem, remove]
  )

  // Stable refs so per-item toggle closures don't need to be recreated when
  // onToggleKey or fields change — the closure always reads the latest values.
  const onToggleKeyRef = useRef(onToggleKey)
  const fieldsRef = useRef(fields)
  onToggleKeyRef.current = onToggleKey
  fieldsRef.current = fields

  // Per-key stable toggle callbacks keyed by field.key. New entries are only
  // added when the set of keys grows; existing entries remain the same reference.
  const toggleCallbacksRef = useRef<Map<number, () => void>>(new Map())
  const getToggleCallback = useCallback((key: number): () => void => {
    if (!toggleCallbacksRef.current.has(key)) {
      toggleCallbacksRef.current.set(key, () => {
        const allFieldKeys = fieldsRef.current.map((f) => f.key)
        onToggleKeyRef.current(key, allFieldKeys)
      })
    }
    return toggleCallbacksRef.current.get(key)!
  }, [])

  const itemList = useMemo(() => {
    const debugEnabled = isMappingDebugEnabled()
    const startedAt = debugEnabled ? performance.now() : 0
    const acceptedDataIndex = activeFilter ?? undefined

    const nextItemList = fields.map((field, arrayIndex) => {
      const mappingId = getMappingIdByIndex(field.name)
      const isExpanded = expandedKeys === 'all' || expandedKeys.has(field.key)

      return (
        <React.Fragment key={ field.key }>
          <MappingItemWithFilter
            acceptedDataIndex={ acceptedDataIndex }
            activeFilter={ activeFilter }
            add={ add }
            expanded={ isExpanded }
            fieldIndex={ field.name }
            insertIndex={ arrayIndex }
            isNew={ flashIndex === arrayIndex }
            mappingId={ mappingId }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
            onRemoveItem={ makeOnRemoveItem(field.name) }
            onToggle={ getToggleCallback(field.key) }
            remove={ remove }
          />
        </React.Fragment>
      )
    })

    if (debugEnabled) {
      console.debug('[DI][MappingsPanelContent] itemList built', {
        itemCount: nextItemList.length,
        durationMs: Number((performance.now() - startedAt).toFixed(2)),
        activeFilter
      })
    }

    return nextItemList
  }, [fields, add, remove, activeFilter, expandedKeys,
    flashIndex, onDropped,
    onInsertItem, makeOnRemoveItem, getToggleCallback, getMappingIdByIndex])

  return (
    <>
      <Flex
        align="center"
        className={ styles.mappingsHeader }
        gap="small"
      >
        <Text className={ styles.mappingsTitle }>
          { t('data-importer.mapping.title-short') }
        </Text>

        <Flex
          align="center"
          className={ styles.mappingsActions }
          gap="extra-small"
        >
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
            onClick={ onOpenAutofillSuggestions }
            type="default"
          >
            { t('data-importer.mapping.auto-fill') }
          </IconTextButton>

          { hasItems && (
            <Button
              onClick={ () => { onCollapseAll(visibleKeys) } }
              size="small"
              style={ { marginLeft: 'auto' } }
              type="link"
            >
              { allVisibleCollapsed
                ? t('data-importer.mapping.expand-all')
                : t('data-importer.mapping.collapse-all') }
            </Button>
          ) }
        </Flex>
      </Flex>

      <Flex
        className={ styles.mappingsContent }
        vertical
      >
        { !hasItems && activeFilter === null && (
          <EmptyDropZone
            add={ add }
            onDropped={ onDropped }
            onInsertItem={ onInsertItem }
          />
        ) }

        { itemList }

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
      </Flex>
    </>
  )
})

MappingsPanelContent.displayName = 'MappingsPanelContent'
