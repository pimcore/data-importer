/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useRef, useState } from 'react'
import { Flex, Form } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem, type ClassAttribute } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingsPanelContent } from './mappings-panel-content'

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
    <Flex
      className={ styles.panel }
      vertical
    >
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
    </Flex>
  )
}
