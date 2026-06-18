/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

 

import React, { useMemo, useState, useEffect, useRef } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { container } from '@pimcore/studio-ui-bundle'
import { Dropdown, Flex, IconTextButton } from '@pimcore/studio-ui-bundle/components'
import { type DragEndEvent, type DragStartEvent } from '@dnd-kit/core'
import { v4 as uuid } from 'uuid'
import { type TransformationPipelineItem } from '../../../../../types'
import { type DynamicTypeTransformerRegistry } from '../../../../../dynamic-types/transformer'
import { bundleServiceIds } from '../../../../../../../config/service-ids'
import { useStyles } from './step-transformations.styles'
import { type PipelineItemWithId } from './transformer-card/transformer-card'
import { TransformersDndList } from './transformers-dnd-list'
import { StepTransformationsRightColumn } from './step-transformations-right-column'

const enrichWithId = (item: TransformationPipelineItem): PipelineItemWithId => ({
  ...item,
  _id: uuid()
})

const stripId = ({ _id: _discardedId, ...rest }: PipelineItemWithId): TransformationPipelineItem => rest

export interface StepTransformationsProps {
  pipeline: TransformationPipelineItem[]
  dataSourceIndex: string[]
  columnHeaderOptions: Array<{ value: string, label: string }>
  onPipelineChange: (next: TransformationPipelineItem[]) => void
  onDataSourceIndexChange: (v: string[]) => void
}

export const StepTransformations = ({
  pipeline,
  dataSourceIndex,
  columnHeaderOptions,
  onPipelineChange,
  onDataSourceIndexChange
}: StepTransformationsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  /**
   * Local array with stable UUIDs attached.
   * Synced with `pipeline` prop: new items get a fresh UUID; existing items
   * keep their UUID (matched by position) so dnd-kit stays stable.
   */
  const [items, setItems] = useState<PipelineItemWithId[]>(() =>
    pipeline.map(enrichWithId)
  )

  const prevPipelineRef = useRef(pipeline)

  useEffect(() => {
    if (pipeline === prevPipelineRef.current) return
    prevPipelineRef.current = pipeline

    setItems(prev => {
      return pipeline.map((item, i) => {
        const existing = prev[i]
        if (existing?.type === item.type) {
          return { ...item, _id: existing._id }
        }
        return enrichWithId(item)
      })
    })
  }, [pipeline])

  const [editingSource, setEditingSource] = useState(false)
  const [activeId, setActiveId] = useState<string | null>(null)

  const handleDragStart = (event: DragStartEvent): void => {
    setActiveId(String(event.active.id))
  }

  const handleDragEnd = (event: DragEndEvent): void => {
    setActiveId(null)
    const { active, over } = event
    if (over === null || active.id === over.id) return

    const oldIndex = items.findIndex(it => it._id === active.id)
    const newIndex = items.findIndex(it => it._id === over.id)
    if (oldIndex === -1 || newIndex === -1) return

    const next = [...items]
    const [moved] = next.splice(oldIndex, 1)
    next.splice(newIndex, 0, moved)

    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  const transformerRegistry = useMemo(
    () => container.get<DynamicTypeTransformerRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry']),
    []
  )

  const transformerDropdownItems = useMemo((): Array<{ key: string, label: string, children: Array<{ key: string, label: string }> }> => {
    const allTypes = transformerRegistry.getDynamicTypes()
    const byGroup = (group: string): Array<{ key: string, label: string }> => allTypes
      .filter(t => t.group === group)
      .map(t => ({ key: t.id, label: t.label }))

    return [
      { key: 'dataManipulation', label: t('data-importer.mapping.advanced-modal.transformer.group.data-manipulation'), children: byGroup('dataManipulation') },
      { key: 'dataTypes', label: t('data-importer.mapping.advanced-modal.transformer.group.data-types'), children: byGroup('dataTypes') },
      { key: 'loadImport', label: t('data-importer.mapping.advanced-modal.transformer.group.load-import'), children: byGroup('loadImport') }
    ]
  }, [transformerRegistry])

  const addTransformer = (type: string): void => {
    const newItem = enrichWithId({ type, settings: {} })
    const next = [...items, newItem]
    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  const removeTransformer = (index: number): void => {
    const next = items.filter((_, i) => i !== index)
    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  const updateTransformerSettings = (index: number, settings: Record<string, any>): void => {
    const next = items.map((it, i) => i === index ? { ...it, settings } : it)
    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  const getSourceLabel = (value: string): string => {
    const opt = columnHeaderOptions.find(o => o.value === value)
    return opt?.label ?? value
  }

  return (
    <Flex
      className={ styles.twoColumnLayout }
      gap="extra-small"
    >

      <Flex
        className={ styles.leftColumn }
        gap="extra-small"
        vertical
      >

        <Flex
          align="center"
          className={ styles.listHeader }
          gap="extra-small"
        >
          <span className={ styles.listHeaderTitle }>
            { t('data-importer.mapping.advanced-modal.step-transformations') }
          </span>

          <Dropdown
            menu={ {
              items: transformerDropdownItems,
              onClick: ({ key }) => { addTransformer(String(key)) }
            } }
            trigger={ ['click'] }
          >
            <IconTextButton
              icon={ { value: 'add' } }
              size="small"
              type="default"
            >
              { t('data-importer.mapping.add') }
            </IconTextButton>
          </Dropdown>
        </Flex>

        <Flex
          className={ styles.itemsList }
          gap={ 6 }
          vertical
        >
          { items.length === 0 && (
            <span className={ styles.emptyState }>
              { t('data-importer.mapping.advanced-modal.no-transformers') }
            </span>
          ) }

          <TransformersDndList
            activeId={ activeId }
            items={ items }
            onDragEnd={ handleDragEnd }
            onDragStart={ handleDragStart }
            onRemove={ removeTransformer }
            onUpdateSettings={ updateTransformerSettings }
            transformerRegistry={ transformerRegistry }
          />
        </Flex>
      </Flex>

      <StepTransformationsRightColumn
        columnHeaderOptions={ columnHeaderOptions }
        dataSourceIndex={ dataSourceIndex }
        editingSource={ editingSource }
        getSourceLabel={ getSourceLabel }
        onBlurSource={ () => { setEditingSource(false) } }
        onChangeSource={ (v) => {
          onDataSourceIndexChange(v)
          setEditingSource(false)
        } }
        onToggleEditing={ () => { setEditingSource(v => !v) } }
      />

    </Flex>
  )
}
