/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useRef } from 'react'
import { Form } from '@pimcore/studio-ui-bundle/components'
import { type DataImporterFormValues, type MappingConfigItem } from '../../../../../types'
import { useMappingItemContext } from '../mapping-item-context'
import { useStyles } from '../mapping-step.styles'
import { MappingDropZone } from './mapping-drop-zone'
import { MappingItem } from './mapping-item'

function isMappingDebugEnabled (): boolean {
  return (globalThis as any).__DI_MAPPING_DEBUG__ === true
}

export interface MappingItemWithFilterProps {
  fieldIndex: number
  mappingId: string
  insertIndex: number
  remove: (index: number) => void
  onRemoveItem: (index: number) => void
  expanded: boolean
  onToggle: () => void
  activeFilter: string | null
  isNew: boolean
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onDropped: (insertIndex: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  acceptedDataIndex?: string
}

export const MappingItemWithFilter = React.memo(({
  fieldIndex,
  mappingId,
  insertIndex,
  remove,
  onRemoveItem,
  expanded,
  onToggle,
  activeFilter,
  isNew,
  add,
  onDropped,
  onInsertItem,
  acceptedDataIndex
}: MappingItemWithFilterProps): React.JSX.Element => {
  const renderCountRef = React.useRef(0)
  renderCountRef.current += 1

  if (isMappingDebugEnabled() && (renderCountRef.current === 1 || renderCountRef.current % 50 === 0)) {
    console.debug('[DI][MappingItemWithFilter] render', {
      fieldIndex,
      mappingId,
      renderCount: renderCountRef.current,
      expanded,
      activeFilter
    })
  }

  const { configName, classId, columnHeaderOptions, attributesMap } = useMappingItemContext()
  const { styles, cx } = useStyles()

  const form = Form.useFormInstance()

  // `fieldIndex` shifts whenever a mapping is inserted or removed above this one, but
  // `Form.useWatch` registers its watcher exactly once — rc-field-form explicitly does not
  // support a dynamic name path. A plain `useWatch(['mappingConfig', fieldIndex])` therefore
  // keeps the value it read for the *previous* index: right after an add/remove every row
  // below the change renders a neighbouring row's label, sources and data target.
  //
  // Subscribing through a selector that reads the index from a ref keeps that single
  // registration valid for value edits, and the value itself is read from the form during
  // render so it always belongs to the CURRENT index. Structural changes always re-render
  // this component (the field's `name` changes), so the fresh read cannot go stale.
  const fieldIndexRef = useRef(fieldIndex)
  fieldIndexRef.current = fieldIndex

  Form.useWatch((values: DataImporterFormValues) => values?.mappingConfig?.[fieldIndexRef.current])

  const item: MappingConfigItem =
    (form.getFieldValue(['mappingConfig', fieldIndex]) as MappingConfigItem | undefined) ?? {}

  const dataSourceIndex = item.dataSourceIndex
  const isHidden = activeFilter !== null && !(dataSourceIndex ?? []).includes(activeFilter)

  return (
    <div className={ cx(isHidden && styles.hiddenItem) }>
      <MappingDropZone
        acceptedDataIndex={ acceptedDataIndex }
        add={ add }
        insertIndex={ insertIndex }
        onDropped={ onDropped }
        onInsertItem={ onInsertItem }
      />
      <div className={ cx(isNew && styles.mappingItemNew) }>
        <MappingItem
          attributesMap={ attributesMap }
          classId={ classId }
          columnHeaderOptions={ columnHeaderOptions }
          configName={ configName }
          dataSourceIndex={ dataSourceIndex }
          expanded={ expanded }
          fieldIndex={ fieldIndex }
          itemLabel={ item.label }
          language={ item.dataTarget?.settings?.language }
          mappingId={ mappingId }
          onRemoveItem={ onRemoveItem }
          onToggle={ onToggle }
          remove={ remove }
          selectedFieldName={ item.dataTarget?.settings?.fieldName }
          transformationResultType={ item.transformationResultType }
        />
      </div>
    </div>
  )
})

MappingItemWithFilter.displayName = 'MappingItemWithFilter'
