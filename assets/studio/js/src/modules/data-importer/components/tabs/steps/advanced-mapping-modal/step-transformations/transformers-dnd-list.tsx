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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { closestCenter, DndContext, DragOverlay, KeyboardSensor, PointerSensor, type DragEndEvent, type DragStartEvent, useSensor, useSensors } from '@dnd-kit/core'
import { SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { restrictToVerticalAxis } from '@dnd-kit/modifiers'
import { type DynamicTypeTransformerRegistry } from '../../../../../dynamic-types/transformer'
import { useStyles } from './step-transformations.styles'
import { CardContent, SortableCard, type PipelineItemWithId } from './transformer-card/transformer-card'

export interface TransformersDndListProps {
  items: PipelineItemWithId[]
  collapsedCards: Record<string, boolean>
  activeId: string | null
  transformerRegistry: DynamicTypeTransformerRegistry
  onDragStart: (event: DragStartEvent) => void
  onDragEnd: (event: DragEndEvent) => void
  onToggleCollapse: (index: number) => void
  onRemove: (index: number) => void
  onUpdateSettings: (index: number, settings: Record<string, any>) => void
}

export const TransformersDndList = ({
  items,
  collapsedCards,
  activeId,
  transformerRegistry,
  onDragStart,
  onDragEnd,
  onToggleCollapse,
  onRemove,
  onUpdateSettings
}: TransformersDndListProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  return (
    <DndContext
      collisionDetection={ closestCenter }
      modifiers={ [restrictToVerticalAxis] }
      onDragEnd={ onDragEnd }
      onDragStart={ onDragStart }
      sensors={ sensors }
    >
      <SortableContext
        items={ items.map(it => it._id) }
        strategy={ verticalListSortingStrategy }
      >
        { items.map((item, index) => {
          const itemType = typeof item.type === 'string' ? item.type : ''
          const transformerType = transformerRegistry.getDynamicType(itemType)
          const isCollapsed = collapsedCards[item._id]
          const itemSettings = (item.settings ?? {})

          return (
            <SortableCard
              collapseTooltip={ isCollapsed
                ? t('data-importer.mapping.expand-all')
                : t('data-importer.mapping.collapse-all') }
              index={ index }
              isCollapsed={ isCollapsed }
              item={ item }
              key={ item._id }
              label={ transformerType?.label ?? itemType }
              onRemove={ onRemove }
              onToggleCollapse={ onToggleCollapse }
              removeTooltip={ t('data-importer.mapping.advanced-modal.transformer.remove') }
            >
              { transformerType?.renderSettings(itemSettings, (s) => { onUpdateSettings(index, s) }) }
            </SortableCard>
          )
        }) }
      </SortableContext>

      <DragOverlay>
        { (() => {
          if (activeId === null) return null
          const activeIndex = items.findIndex(it => it._id === activeId)
          if (activeIndex === -1) return null
          const activeItem = items[activeIndex]
          const activeItemType = typeof activeItem.type === 'string' ? activeItem.type : ''
          const transformerType = transformerRegistry.getDynamicType(activeItemType)
          const isCollapsed = collapsedCards[activeItem._id]
          const activeItemSettings = (activeItem.settings ?? {})
          return (
            <div className={ styles.transformerCardOverlay }>
              <CardContent
                collapseTooltip={ isCollapsed
                  ? t('data-importer.mapping.expand-all')
                  : t('data-importer.mapping.collapse-all') }
                index={ activeIndex }
                isCollapsed={ isCollapsed }
                label={ transformerType?.label ?? activeItemType }
                onRemove={ onRemove }
                onToggleCollapse={ onToggleCollapse }
                removeTooltip={ t('data-importer.mapping.advanced-modal.transformer.remove') }
              >
                { transformerType?.renderSettings(activeItemSettings, (s) => { onUpdateSettings(activeIndex, s) }) }
              </CardContent>
            </div>
          )
        })() }
      </DragOverlay>
    </DndContext>
  )
}
