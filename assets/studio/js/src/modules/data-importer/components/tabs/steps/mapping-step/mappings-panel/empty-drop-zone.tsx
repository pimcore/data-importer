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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'

export interface EmptyDropZoneProps {
  add: (value?: MappingConfigItem, insertIndex?: number) => void
  onInsertItem: (add: (value?: MappingConfigItem, insertIndex?: number) => void, insertIndex: number, dataIndex: string, label: string) => void
  onDropped: (insertIndex: number) => void
}

export const EmptyDropZone = ({ add, onInsertItem, onDropped }: EmptyDropZoneProps): React.JSX.Element => {
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
