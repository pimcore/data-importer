/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback } from 'react'
import { Droppable, type DragAndDropInfo, Form, IconTextButton } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'
import { isDropAllowed } from '../utils/mapping-drop-policy'

export interface FilteredEmptyStateProps {
  activeFilter: string
  activeFilterLabel: string | null
  fields: Array<{ name: number, key: number }>
  onAddMappingForFilter: () => void
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
}

export const FilteredEmptyState = ({
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
