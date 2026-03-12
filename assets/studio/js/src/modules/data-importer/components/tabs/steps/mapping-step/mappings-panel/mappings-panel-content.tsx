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
import { type MappingConfigItem, type ClassAttribute } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingDropZone, MappingItemWithFilter } from '../mapping-item'
import { EmptyDropZone } from './empty-drop-zone'
import { FilteredEmptyState } from './filtered-empty-state'

export interface MappingsPanelContentProps {
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
  expandedKeys: ReadonlySet<number> | 'all'
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

export const MappingsPanelContent = React.memo(({
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

  const visibleKeys = useMemo(
    () => fields.map((f) => f.key),
    [fields]
  )

  const makeOnRemoveItem = useCallback(
    (index: number) => () => { onRemoveItem(remove, index) },
    [onRemoveItem, remove]
  )

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
            onClick={ () => { onAutoFill(fields, add) } }
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
