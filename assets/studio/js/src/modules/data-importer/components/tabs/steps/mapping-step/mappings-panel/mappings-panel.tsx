/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useMemo, useRef, useState } from 'react'
import { Flex, Form } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingsPanelContent } from './mappings-panel-content'

export interface MappingsPanelProps {
  activeFilter: string | null
  activeFilterLabel: string | null
  /** Set of field.key values that are currently expanded, or 'all' if everything is expanded */
  expandedKeys: ReadonlySet<number> | 'all'
  /** True while an expand-all transition is in flight — used to flip the button label eagerly */
  expandAllPending: boolean
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
  activeFilter,
  activeFilterLabel,
  expandedKeys,
  expandAllPending,
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

  const [flashIndex, setFlashIndex] = useState<number | null>(null)
  const flashTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const handleDropped = useCallback((insertIndex: number): void => {
    if (flashTimerRef.current !== null) clearTimeout(flashTimerRef.current)
    setFlashIndex(insertIndex)
    flashTimerRef.current = setTimeout(() => { setFlashIndex(null) }, 400)
  }, [])

  // Form.List's render prop gives new `add`/`remove` function references on every
  // render. Wrapping them in refs and exposing stable callbacks prevents those
  // references from busting React.memo on MappingsPanelContent (and the itemList
  // memo inside it) on every unrelated re-render.
  const addRef = useRef<(value?: MappingConfigItem, insertIndex?: number) => void>(() => undefined)
  const removeRef = useRef<(index: number) => void>(() => undefined)

  const stableAdd = useMemo<(value?: MappingConfigItem, insertIndex?: number) => void>(
    () => (value, insertIndex) => { addRef.current(value, insertIndex) },
  []
  )
  const stableRemove = useMemo<(index: number) => void>(
    () => (index) => { removeRef.current(index) },
  []
  )

  // Form.List re-renders whenever any value inside mappingConfig changes, producing
  // a new `fields` array reference even when the list structure (length + keys) is
  // identical. Stabilize it so the itemList memo in MappingsPanelContent only busts
  // on actual structural changes (add/remove/move), not on field-value edits.
  const stableFieldsRef = useRef<Array<{ name: number, key: number }>>([])
  const stabilizeFields = useCallback(
    (incoming: Array<{ name: number, key: number }>): Array<{ name: number, key: number }> => {
      const prev = stableFieldsRef.current
      if (
        prev.length === incoming.length &&
        incoming.every((f, i) => f.key === prev[i].key && f.name === prev[i].name)
      ) {
        return prev
      }
      stableFieldsRef.current = incoming
      return incoming
    },
    []
  )

  return (
    <Flex
      className={ styles.panel }
      vertical
    >
      <Form.List name="mappingConfig">
        { (rawFields, { add, remove }) => {
          // Keep refs current so the stable wrappers always delegate to the latest
          // functions provided by Form.List.
          addRef.current = add
          removeRef.current = remove

          const fields = stabilizeFields(rawFields)
          const hasItems = fields.length > 0
          // When an expand-all transition is in flight, treat as "all expanded"
          // so the button label flips to "Collapse All" immediately.
          const allVisibleCollapsed = !expandAllPending &&
            expandedKeys !== 'all' &&
            fields.every((f) => !expandedKeys.has(f.key))

          return (
            <MappingsPanelContent
              activeFilter={ activeFilter }
              activeFilterLabel={ activeFilterLabel }
              add={ stableAdd }
              allVisibleCollapsed={ allVisibleCollapsed }
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
              remove={ stableRemove }
            />
          )
        } }
      </Form.List>
    </Flex>
  )
}
