/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { Form } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem, type ClassAttribute } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { MappingDropZone } from './mapping-drop-zone'
import { MappingItem } from './mapping-item'

export interface MappingItemWithFilterProps {
  fieldIndex: number
  mappingId: string
  insertIndex: number
  remove: (index: number) => void
  onRemoveItem: (index: number) => void
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
  classId: string | undefined
  expanded: boolean
  onToggle: () => void
  attributesMap: Record<string, ClassAttribute[]>
  activeFilter: string | null
  isNew: boolean
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onDropped: (insertIndex: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  acceptedDataIndex?: string
}

export const MappingItemWithFilter = React.memo((props: MappingItemWithFilterProps): React.JSX.Element => {
  const {
    fieldIndex,
    mappingId,
    insertIndex,
    activeFilter,
    isNew,
    add,
    onDropped,
    onInsertItem,
    acceptedDataIndex,
    attributesMap,
    expanded,
    onToggle,
    ...itemProps
  } = props

  const { styles, cx } = useStyles()
  const form = Form.useFormInstance()

  const mappingItems = (Form.useWatch('mappingConfig') as MappingConfigItem[] | undefined) ?? []
  const itemById = mappingItems.find((entry) => entry.mappingId === mappingId)
  const itemByIndex = form.getFieldValue(['mappingConfig', fieldIndex]) as MappingConfigItem | undefined
  const item: MappingConfigItem = itemById ?? itemByIndex ?? {}

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
          { ...itemProps }
          attributesMap={ attributesMap }
          dataSourceIndex={ dataSourceIndex }
          expanded={ expanded }
          fieldIndex={ fieldIndex }
          itemLabel={ item.label }
          mappingId={ mappingId }
          onToggle={ onToggle }
          selectedFieldName={ item.dataTarget?.settings?.fieldName }
          transformationResultType={ item.transformationResultType }
        />
      </div>
    </div>
  )
})

MappingItemWithFilter.displayName = 'MappingItemWithFilter'
