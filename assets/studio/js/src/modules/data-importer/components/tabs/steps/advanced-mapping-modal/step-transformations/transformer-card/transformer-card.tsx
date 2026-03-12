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
import { CollapseItem, Flex, IconButton } from '@pimcore/studio-ui-bundle/components'
import { CSS } from '@dnd-kit/utilities'
import { useSortable } from '@dnd-kit/sortable'
import { type TransformationPipelineItem } from '../../../../../../types'
import { useStyles } from './transformer-card.styles'

export interface PipelineItemWithId extends TransformationPipelineItem {
  _id: string
}

export interface CardContentProps {
  label: string
  children: React.ReactNode
  dragHandleProps?: React.HTMLAttributes<HTMLDivElement>
  index: number
  onRemove: (index: number) => void
  removeTooltip: string
}

export const CardContent = (props: CardContentProps): React.JSX.Element => {
  const {
    label,
    children,
    dragHandleProps,
    index,
    onRemove,
    removeTooltip
  } = props
  const { styles } = useStyles()

  const cardLabel = (
    <Flex
      align="center"
      gap="mini"
    >
      {/* Drag handle */}
      <Flex
        align="center"
        className={ styles.dragHandle }
        { ...dragHandleProps }
      >
        <span className={ styles.dragHandleIcon }>{ '⠿' }</span>
      </Flex>

      <span className={ styles.transformerLabel }>
        { label }
      </span>
    </Flex>
  )

  const removeButton = (
    <IconButton
      className={ styles.transformerDeleteButton }
      icon={ { value: 'trash' } }
      onClick={ (e) => { e.stopPropagation(); onRemove(index) } }
      size="small"
      tooltip={ { title: removeTooltip } }
      type="text"
    />
  )

  return (
    <div className={ styles.transformerCardWrapper }>
      <CollapseItem
        bordered={ false }
        defaultActive
        extra={ removeButton }
        hasContentSeparator={ false }
        label={ cardLabel }
        size="small"
        theme="primary"
      >
        { children }
      </CollapseItem>
    </div>
  )
}

export interface SortableCardProps {
  item: PipelineItemWithId
  index: number
  label: string
  children: React.ReactNode
  onRemove: (index: number) => void
  removeTooltip: string
}

export const SortableCard = (props: SortableCardProps): React.JSX.Element => {
  const {
    item,
    index,
    label,
    children,
    onRemove,
    removeTooltip
  } = props

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
      ref={ setNodeRef }
      style={ style }
    >
      <CardContent
        dragHandleProps={ { ...attributes, ...listeners } }
        index={ index }
        label={ label }
        onRemove={ onRemove }
        removeTooltip={ removeTooltip }
      >
        { children }
      </CardContent>
    </div>
  )
}
