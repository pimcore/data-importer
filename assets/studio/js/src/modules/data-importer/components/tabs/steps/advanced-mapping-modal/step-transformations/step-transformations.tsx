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
import { Select, Dropdown, IconTextButton, IconButton } from '@pimcore/studio-ui-bundle/components'
import { DndContext, DragOverlay, type DragEndEvent, type DragStartEvent } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, sortableKeyboardCoordinates } from '@dnd-kit/sortable'
import { useSensors, useSensor, PointerSensor, KeyboardSensor, closestCenter } from '@dnd-kit/core'
import { restrictToVerticalAxis } from '@dnd-kit/modifiers'
import { v4 as uuid } from 'uuid'
import { type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig, type MappingConfigItem, type TransformationPipelineItem } from '../../../../../types'
import { type DynamicTypeTransformerRegistry } from '../../../../../dynamic-types/transformer'
import { bundleServiceIds } from '../../../../../../../config/service-ids'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { useSharedStepStyles } from '../step-shared.styles'
import { useStyles } from './step-transformations.styles'
import { CardContent, SortableCard, type PipelineItemWithId } from './transformer-card/transformer-card'

/** Enrich a raw pipeline item with a fresh UUID */
const enrichWithId = (item: TransformationPipelineItem): PipelineItemWithId => ({
  ...item,
  _id: uuid()
})

/** Strip the client-side _id before passing back to the parent */
const stripId = ({ _id: _, ...rest }: PipelineItemWithId): TransformationPipelineItem => rest

// ── StepTransformations ───────────────────────────────────────────────────────

export interface StepTransformationsProps {
  configName: string
  pipeline: TransformationPipelineItem[]
  dataSourceIndex: string[]
  columnHeaderOptions: Array<{ value: string, label: string }>
  previewRefreshToken: number
  /** Live snapshot of the full mapping item — forwarded to PreviewResultPanel */
  currentMappingItem: MappingConfigItem
  /** Saved loaderConfig + interpreterConfig + resolverConfig — needed so the backend can interpret the preview file */
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  onPipelineChange: (next: TransformationPipelineItem[]) => void
  onDataSourceIndexChange: (v: string[]) => void
  onPrev: () => void
  onNext: () => void
}

export const StepTransformations = ({
  configName,
  pipeline,
  dataSourceIndex,
  columnHeaderOptions,
  previewRefreshToken,
  currentMappingItem,
  baseConfig,
  onPipelineChange,
  onDataSourceIndexChange,
  onPrev,
  onNext
}: StepTransformationsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: shared } = useSharedStepStyles()

  // ── Internal state ──────────────────────────────────────────────────────────

  /**
   * Local array with stable UUIDs attached.
   * Synced with `pipeline` prop: new items get a fresh UUID; existing items
   * keep their UUID (matched by position) so dnd-kit stays stable.
   */
  const [items, setItems] = useState<PipelineItemWithId[]>(() =>
    pipeline.map(enrichWithId)
  )

  /**
   * Track the previous pipeline reference so we can detect external changes
   * (e.g. parent resets the pipeline) and re-sync without losing existing IDs.
   */
  const prevPipelineRef = useRef(pipeline)

  useEffect(() => {
    if (pipeline === prevPipelineRef.current) return
    prevPipelineRef.current = pipeline

    // Merge: keep existing _id for unchanged positions, add fresh UUID for new items
    setItems(prev => {
      return pipeline.map((item, i) => {
        const existing = prev[i]
        if (existing !== undefined && existing.type === item.type) {
          return { ...item, _id: existing._id }
        }
        return enrichWithId(item)
      })
    })
  }, [pipeline])

  const [collapsedCards, setCollapsedCards] = useState<Record<string, boolean>>({})
  const [editingSource, setEditingSource] = useState(false)
  const [activeId, setActiveId] = useState<string | null>(null)

  // ── DnD setup ───────────────────────────────────────────────────────────────

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

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

  // ── Registry ────────────────────────────────────────────────────────────────

  const transformerRegistry = useMemo(
    () => container.get<DynamicTypeTransformerRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry']),
    []
  )

  const transformerDropdownItems = useMemo(() => {
    const allTypes = transformerRegistry.getAllTypes()
    const byGroup = (group: string) => allTypes
      .filter(t => t.group === group)
      .map(t => ({ key: t.id, label: t.label }))

    return [
      { key: 'dataManipulation', label: t('data-importer.mapping.advanced-modal.transformer.group.data-manipulation'), children: byGroup('dataManipulation') },
      { key: 'dataTypes', label: t('data-importer.mapping.advanced-modal.transformer.group.data-types'), children: byGroup('dataTypes') },
      { key: 'loadImport', label: t('data-importer.mapping.advanced-modal.transformer.group.load-import'), children: byGroup('loadImport') }
    ]
  }, [transformerRegistry])

  // ── Pipeline mutations ──────────────────────────────────────────────────────

  const addTransformer = (type: string): void => {
    const newItem = enrichWithId({ type, settings: {} })
    const next = [...items, newItem]
    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  const removeTransformer = (index: number): void => {
    const removed = items[index]
    const next = items.filter((_, i) => i !== index)
    setItems(next)
    // Clean up collapse state for removed item
    setCollapsedCards(prev => {
      const updated = { ...prev }
      delete updated[removed._id]
      return updated
    })
    onPipelineChange(next.map(stripId))
  }

  const updateTransformerSettings = (index: number, settings: Record<string, any>): void => {
    const next = items.map((it, i) => i === index ? { ...it, settings } : it)
    setItems(next)
    onPipelineChange(next.map(stripId))
  }

  // ── Collapse helpers ────────────────────────────────────────────────────────

  const toggleCard = (index: number): void => {
    const id = items[index]?._id
    if (id === undefined) return
    setCollapsedCards(prev => ({ ...prev, [id]: !prev[id] }))
  }

  const allCollapsed = items.length > 0 && items.every(it => collapsedCards[it._id])

  const collapseAll = (): void => {
    if (allCollapsed) {
      setCollapsedCards({})
    } else {
      const next: Record<string, boolean> = {}
      items.forEach(it => { next[it._id] = true })
      setCollapsedCards(next)
    }
  }

  // ── Source label helper ─────────────────────────────────────────────────────

  const getSourceLabel = (value: string): string => {
    const opt = columnHeaderOptions.find(o => o.value === value)
    return opt?.label ?? value
  }

  // ── Render ──────────────────────────────────────────────────────────────────

  return (
    <div className={ styles.twoColumnLayout }>

      {/* LEFT: Transformations list */}
      <div className={ styles.leftColumn }>

        {/* Header row: "Transformations" + "+ New" dropdown + "Collapse all" link */}
        <div className={ styles.listHeader }>
          <span className={ styles.listHeaderTitle }>
            { t('data-importer.mapping.advanced-modal.step-transformations') }
          </span>

          <Dropdown
            menu={ {
              items: transformerDropdownItems,
              onClick: ({ key }) => { addTransformer(key) }
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

          { items.length > 0 && (
            <span
              className={ styles.collapseAllLink }
              onClick={ collapseAll }
            >
              { allCollapsed
                ? t('data-importer.mapping.expand-all')
                : t('data-importer.mapping.collapse-all') }
            </span>
          ) }
        </div>

        {/* Sortable list of transformer cards */}
        <div className={ styles.itemsList }>
          { items.length === 0 && (
            <span className={ styles.emptyState }>
              { t('data-importer.mapping.advanced-modal.no-transformers') }
            </span>
          ) }

          <DndContext
            collisionDetection={ closestCenter }
            modifiers={ [restrictToVerticalAxis] }
            onDragEnd={ handleDragEnd }
            onDragStart={ handleDragStart }
            sensors={ sensors }
          >
            <SortableContext
              items={ items.map(it => it._id) }
              strategy={ verticalListSortingStrategy }
            >
              { items.map((item, index) => {
                const transformerType = transformerRegistry.getDynamicType(item.type)
                const isCollapsed = collapsedCards[item._id]

                return (
                  <SortableCard
                    collapseTooltip={ isCollapsed
                      ? t('data-importer.mapping.expand-all')
                      : t('data-importer.mapping.collapse-all') }
                    index={ index }
                    isCollapsed={ isCollapsed }
                    item={ item }
                    key={ item._id }
                    label={ transformerType?.label ?? item.type }
                    onRemove={ removeTransformer }
                    onToggleCollapse={ toggleCard }
                    removeTooltip={ t('data-importer.mapping.advanced-modal.transformer.remove') }
                  >
                    { transformerType?.renderSettings(
                      item.settings ?? {},
                      (s) => { updateTransformerSettings(index, s) }
                    ) }
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
                const transformerType = transformerRegistry.getDynamicType(activeItem.type)
                const isCollapsed = collapsedCards[activeItem._id]
                return (
                  <div className={ styles.transformerCardOverlay }>
                    <CardContent
                      collapseTooltip={ isCollapsed
                        ? t('data-importer.mapping.expand-all')
                        : t('data-importer.mapping.collapse-all') }
                      index={ activeIndex }
                      isCollapsed={ isCollapsed }
                      label={ transformerType?.label ?? activeItem.type }
                      onRemove={ removeTransformer }
                      onToggleCollapse={ toggleCard }
                      removeTooltip={ t('data-importer.mapping.advanced-modal.transformer.remove') }
                    >
                      { transformerType?.renderSettings(
                        activeItem.settings ?? {},
                        (s) => { updateTransformerSettings(activeIndex, s) }
                      ) }
                    </CardContent>
                  </div>
                )
              })() }
            </DragOverlay>
          </DndContext>
        </div>
      </div>

      {/* RIGHT: Source Attributes + Mapping preview + nav buttons */}
      <div className={ styles.rightColumn }>
        <div className={ styles.rightColumnTop }>

          {/* Source Attribute(s) — read-only display with pencil to edit */}
          <div className={ styles.sourceSection }>
            <div className={ styles.sourceSectionHeader }>
              <span className={ styles.sourceSectionTitle }>
                { t('data-importer.mapping.advanced-modal.step-source.label') }
              </span>
              <IconButton
                icon={ { value: 'edit-pen' } }
                onClick={ () => { setEditingSource(v => !v) } }
                size="small"
                tooltip={ { title: t('data-importer.mapping.advanced-modal.transformer.edit-source') } }
                type="text"
              />
            </div>

            { editingSource
              ? (
                <Select
                  className={ shared.selectFull }
                  mode="multiple"
                  onBlur={ () => { setEditingSource(false) } }
                  onChange={ (v) => { onDataSourceIndexChange(v); setEditingSource(false) } }
                  options={ columnHeaderOptions }
                  showSearch
                  value={ dataSourceIndex }
                />
                )
              : (
                <div className={ styles.sourceValues }>
                  { dataSourceIndex.length === 0
                    ? <span className={ styles.emptyState }>{ '—' }</span>
                    : dataSourceIndex.map((v, i) => (
                      <React.Fragment key={ v }>
                        { i > 0 && <span className={ styles.sourceSeparator }>{ ' | ' }</span> }
                        <span>{ getSourceLabel(v) }</span>
                      </React.Fragment>
                    ))
                  }
                </div>
                )
            }
          </div>

          {/* Mapping preview — live panel */}
          <div className={ styles.previewWrapper }>
            <PreviewPanel
              baseConfig={ baseConfig }
              configName={ configName }
              currentMappingItem={ currentMappingItem }
              mode="result"
              refreshToken={ previewRefreshToken }
            />
          </div>
        </div>

        {/* Previous / Next step buttons — bottom right */}
        <div className={ shared.navButtons }>
          <button
            className={ shared.outlineButton }
            onClick={ onPrev }
            type="button"
          >
            { t('data-importer.mapping.advanced-modal.previous-step') }
          </button>
          <button
            className={ shared.outlineButton }
            onClick={ onNext }
            type="button"
          >
            { t('data-importer.mapping.advanced-modal.next-step') }
          </button>
        </div>
      </div>

    </div>
  )
}
