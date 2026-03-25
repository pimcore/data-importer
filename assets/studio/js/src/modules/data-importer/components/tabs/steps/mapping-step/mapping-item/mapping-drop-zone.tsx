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
import { Droppable, type DragAndDropInfo } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'
import { isDropAllowed } from '../utils/mapping-drop-policy'

export interface MappingDropZoneProps {
  insertIndex: number
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
  acceptedDataIndex?: string
}

export const MappingDropZone = ({ insertIndex, add, onInsertItem, onDropped, acceptedDataIndex }: MappingDropZoneProps): React.JSX.Element => {
  const { styles } = useStyles()

  const handleDrop = useCallback((info: DragAndDropInfo): void => {
    const { dataIndex, label } = info.data as { dataIndex: string, label: string }
    onInsertItem(add, insertIndex, dataIndex, label)
    onDropped(insertIndex)
  }, [add, insertIndex, onInsertItem, onDropped])

  return (
    <Droppable
      className={ styles.mappingDropZoneWrapper }
      disableDndActiveIndicator={ false }
      isValidContext={ (info) => {
        if (info.type !== DND_TYPE) return false
        const droppedDataIndex = (info.data as { dataIndex?: string }).dataIndex
        return isDropAllowed(droppedDataIndex, acceptedDataIndex ?? null)
      } }
      onDrop={ handleDrop }
      variant="default"
    >
      <DndClassDiv className={ styles.mappingDropZone } />
    </Droppable>
  )
}
