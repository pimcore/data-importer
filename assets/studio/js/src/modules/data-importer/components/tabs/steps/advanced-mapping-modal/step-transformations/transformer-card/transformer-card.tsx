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
import { IconButton } from '@pimcore/studio-ui-bundle/components'
import { CSS } from '@dnd-kit/utilities'
import { useSortable } from '@dnd-kit/sortable'
import { type TransformationPipelineItem } from '../../../../../../types'
import { useStyles } from './transformer-card.styles'

/** Pipeline item enriched with a stable client-side id for dnd-kit */
export interface PipelineItemWithId extends TransformationPipelineItem {
  _id: string
}

// ── CardContent ────────────────────────────────────────────────────────────────
// Renders the visual card body; used both by SortableCard and the DragOverlay.

export interface CardContentProps {
  label: string
  isCollapsed: boolean
  children: React.ReactNode
  /** When true the drag handle receives dnd-kit attributes/listeners */
  dragHandleProps?: React.HTMLAttributes<HTMLDivElement>
  index: number
  onToggleCollapse: (index: number) => void
  onRemove: (index: number) => void
  removeTooltip: string
  collapseTooltip: string
}

export const CardContent = (props: CardContentProps): React.JSX.Element => {
  const {
    label,
    isCollapsed,
    children,
    dragHandleProps,
    index,
    onToggleCollapse,
    onRemove,
    removeTooltip,
    collapseTooltip
  } = props
  const { styles } = useStyles()

  return (
    <>
      <div className={ styles.transformerCardHeader }>
        {/* Drag handle — only this area is draggable */}
        <div
          className={ styles.dragHandle }
          { ...dragHandleProps }
        >
          <span className={ styles.dragHandleIcon }>{ '⠿' }</span>
        </div>

        {/* Label + inline collapse toggle */}
        <span className={ styles.transformerLabel }>
          { label }
        </span>
        <IconButton
          className={ styles.transformerCollapseIcon }
          icon={ { value: isCollapsed ? 'chevron-down' : 'chevron-up' } }
          onClick={ () => { onToggleCollapse(index) } }
          size="small"
          tooltip={ { title: collapseTooltip } }
          type="text"
        />

        {/* Remove — pushed to far right via margin-left: auto */}
        <IconButton
          className={ styles.transformerDeleteButton }
          icon={ { value: 'trash' } }
          onClick={ () => { onRemove(index) } }
          size="small"
          tooltip={ { title: removeTooltip } }
          type="text"
        />
      </div>

      { !isCollapsed && children }
    </>
  )
}

// ── SortableCard ──────────────────────────────────────────────────────────────

export interface SortableCardProps {
  item: PipelineItemWithId
  index: number
  label: string
  isCollapsed: boolean
  children: React.ReactNode
  onToggleCollapse: (index: number) => void
  onRemove: (index: number) => void
  removeTooltip: string
  collapseTooltip: string
}

export const SortableCard = (props: SortableCardProps): React.JSX.Element => {
  const {
    item,
    index,
    label,
    isCollapsed,
    children,
    onToggleCollapse,
    onRemove,
    removeTooltip,
    collapseTooltip
  } = props
  const { styles } = useStyles()

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging
  } = useSortable({ id: item._id })

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0 : 1
  }

  return (
    <div
      className={ styles.transformerCard }
      ref={ setNodeRef }
      style={ style }
    >
      <CardContent
        collapseTooltip={ collapseTooltip }
        dragHandleProps={ { ...attributes, ...listeners } }
        index={ index }
        isCollapsed={ isCollapsed }
        label={ label }
        onRemove={ onRemove }
        onToggleCollapse={ onToggleCollapse }
        removeTooltip={ removeTooltip }
      >
        { children }
      </CardContent>
    </div>
  )
}
