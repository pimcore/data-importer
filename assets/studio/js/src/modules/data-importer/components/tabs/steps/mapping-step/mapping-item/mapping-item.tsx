/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import {
  Droppable,
  type DragAndDropInfo,
  Form,
  IconButton,
  IconTextButton,
  Input,
  Panel,
  Select
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { type MappingConfigItem, type ClassAttribute, resolveAttrMapKey, type DataImporterFormValues } from '../../../../../types'
import { filterByLabel } from '../../select-utils'
import { useStyles } from '../mapping-step.styles'
import { AdvancedMappingModal } from '../../advanced-mapping-modal'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'
import { ArrowColumn } from './arrow-column/arrow-column'
import { findMappingIndexById } from '../utils/mapping-identity'
import { isDropAllowed } from '../utils/mapping-drop-policy'

// ── MappingDropZone ────────────────────────────────────────────────────────
// Thin horizontal bar rendered above each mapping item. Accepts source column
// drops and inserts a new mapping at the given index.

interface MappingDropZoneProps {
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

export interface MappingItemProps {
  fieldIndex: number
  mappingId: string
  remove: (index: number) => void
  onRemoveItem: (index: number) => void
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
  classId: string | undefined
  /** Whether the panel is expanded (controlled) */
  expanded: boolean
  /** Called when the user clicks the panel header to toggle collapse */
  onToggle: () => void
  // Layout-driving values passed from parent (avoids Form.useWatch stagger on mount)
  itemLabel: string | undefined
  dataSourceIndex: string[] | undefined
  transformationResultType: string | undefined
  selectedFieldName: string | undefined
  // Pre-loaded class attributes keyed by transformationResultType
  attributesMap: Record<string, ClassAttribute[]>
}

export const MappingItem = React.memo(({
  fieldIndex,
  mappingId,
  remove,
  onRemoveItem,
  configName,
  columnHeaderOptions,
  classId,
  expanded,
  onToggle,
  itemLabel,
  dataSourceIndex,
  transformationResultType,
  selectedFieldName,
  attributesMap
}: MappingItemProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const form = Form.useFormInstance()
  const [advancedOpen, setAdvancedOpen] = useState(false)
  const settings = useSettings()
  const languageOptions = useMemo(
    () => (settings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
    [settings.validLanguages]
  )

  // ── Panel state derivation ──────────────────────────────────────────────
  // All values come from props (derived from parent's single Form.useWatch),
  // so they are correct on the very first render — no staggered useWatch updates.
  const sourceCount = (dataSourceIndex ?? []).length
  const hasSource = sourceCount > 0
  const hasDestination = selectedFieldName !== undefined && selectedFieldName !== ''
  const isAdvanced = transformationResultType !== undefined &&
    transformationResultType !== '' &&
    transformationResultType !== 'default'

  // warning: source is set but destination is still empty
  const isWarningState = hasSource && !hasDestination
  // inProgress: multiple sources and destination not yet configured
  const isInProgressState = sourceCount > 1 && !hasDestination

  const getCurrentIndexByMappingId = useCallback((): number => {
    return findMappingIndexById(form, mappingId)
  }, [form, mappingId])

  // Auto-fill label from the first selected source column when label is still empty.
  // Uses mappingId-based lookup so index shifts cannot write into wrong items.
  useEffect(() => {
    const index = getCurrentIndexByMappingId()
    if (index < 0) return

    const item = (form.getFieldValue(['mappingConfig', index]) as MappingConfigItem | undefined) ?? {}
    const currentLabel = item.label ?? ''
    const currentDataSourceIndex = item.dataSourceIndex ?? []

    if (currentLabel === '' && currentDataSourceIndex.length > 0) {
      const match = columnHeaderOptions.find((o) => o.value === currentDataSourceIndex[0])
      if (match !== undefined) {
        form.setFieldValue(['mappingConfig', index, 'label'], match.label, { triggerChange: true })
      }
    }
  }, [columnHeaderOptions, form, getCurrentIndexByMappingId])

  // Look up pre-loaded attributes from attributesMap — no RTK Query hook needed,
  // so data is available on the very first render (no one-frame pop-in).
  const attrMapKey = resolveAttrMapKey(transformationResultType)
  const attributes: ClassAttribute[] = attributesMap[attrMapKey] ?? []
  const attributeOptions = useMemo(
    () => attributes.map((a) => ({ value: a.key, label: a.title })),
    [attributes]
  )

  const selectedAttr = attributes.find((a) => a.key === selectedFieldName)
  const isLocalized = selectedAttr?.localized ?? false

  const panelTitle = (itemLabel !== undefined && itemLabel !== '')
    ? itemLabel
    : t('data-importer.mapping.item.new-label')

  const handleDrop = useCallback((info: DragAndDropInfo): void => {
    const index = getCurrentIndexByMappingId()
    if (index < 0) return

    const droppedDataIndex = (info.data as { dataIndex: string }).dataIndex
    const current = (form.getFieldValue(['mappingConfig', index, 'dataSourceIndex']) ?? []) as string[]
    if (!current.includes(droppedDataIndex)) {
      form.setFieldValue(['mappingConfig', index, 'dataSourceIndex'], [...current, droppedDataIndex], { triggerChange: true })
    }
  }, [form, getCurrentIndexByMappingId])

  return (
    <Droppable
      className={ styles.droppablePanel }
      disableDndActiveIndicator={ false }
      isValidContext={ (info) => info.type === DND_TYPE }
      onDrop={ handleDrop }
      variant="default"
    >
      <DndClassDiv className={ styles.panelDndWrapper }>
        <Panel
          active={ expanded }
          border
          collapsible
          contentPadding="none"
          onChange={ () => { onToggle() } }
          theme="default"
          title={ panelTitle }
        >
          <div className={ styles.mappingItemContent }>
            {/* Hidden: preserve transformationResultType */}
            <Form.Item
              hidden
              name={ [fieldIndex, 'transformationResultType'] }
            >
              <Input />
            </Form.Item>

            {/* Label row: input + Advanced (disabled) + Delete */}
            <div className={ styles.mappingLabelRow }>
              {/* Locally controlled — debounced write-back to form store (300ms).
                  Avoids re-rendering siblings on every keystroke. */}
              <div
                className={ styles.mappingLabelInput }
                style={ { flex: 1 } }
              >
                <Form.Item
                  name={ [fieldIndex, 'label'] }
                  style={ { marginBottom: 0 } }
                >
                  <Input placeholder={ t('data-importer.mapping.item.label') } />
                </Form.Item>
              </div>

              <IconTextButton
                icon={ { value: 'settings' } }
                onClick={ () => { setAdvancedOpen(true) } }
                type="default"
              >
                { t('data-importer.mapping.item.advanced') }
              </IconTextButton>

              <IconButton
                icon={ { value: 'trash' } }
                onClick={ () => { onRemoveItem(fieldIndex) } }
                tooltip={ { title: t('data-importer.mapping.item.delete') } }
                type="default"
              />
            </div>

            {/* Divider between label row and source/destination fields */}
            <div className={ styles.mappingDivider } />

            {/* Source → Destination */}
            <div className={ styles.sourcesDestRow }>
              {/* Source column */}
              <div className={ styles.sourcesDestCol }>
                <div>
                  { t('data-importer.mapping.item.source') }
                </div>
                <div className={ styles.sourceDropZone }>
                  <Form.Item
                    name={ [fieldIndex, 'dataSourceIndex'] }
                    style={ { marginBottom: 0 } }
                  >
                    <Select
                      filterOption={ filterByLabel }
                      mode="multiple"
                      options={ columnHeaderOptions }
                      placeholder={ t('data-importer.mapping.item.source-placeholder') }
                      showSearch
                    />
                  </Form.Item>
                </div>
              </div>

              {/* Arrow column */}
              <ArrowColumn
                isAdvanced={ isAdvanced }
                isInProgressState={ isInProgressState }
                isWarningState={ isWarningState }
              />

              {/* Destination column */}
              <div className={ styles.sourcesDestCol }>
                <div>
                  { t('data-importer.mapping.item.destination') }
                </div>

                { /* Advanced state: show plain text immediately — no attributes needed */ }
                { isAdvanced
                  ? (
                    <>
                      <div className={ styles.destinationTextBlock }>
                        <span>{ selectedAttr?.title ?? selectedFieldName ?? '' }</span>
                      </div>
                      {/* Hidden Form.Item to preserve destination field value */}
                      <Form.Item
                        hidden
                        name={ [fieldIndex, 'dataTarget', 'settings', 'fieldName'] }
                        style={ { display: 'none' } }
                      >
                        <Input />
                      </Form.Item>
                    </>
                    )
                  : isInProgressState
                    ? (
                  /* In-progress state: multi-source, no advanced config — no attributes needed */
                      <>
                        <div className={ styles.requiresAdvancedHint }>
                          { t('data-importer.mapping.item.requires-advanced-setup') }
                        </div>
                        {/* Hidden Form.Item to preserve destination field value */}
                        <Form.Item
                          hidden
                          name={ [fieldIndex, 'dataTarget', 'settings', 'fieldName'] }
                          style={ { display: 'none' } }
                        >
                          <Input />
                        </Form.Item>
                      </>
                      )
                    : (
                  /* Normal state: Select — always rendered, options fill in when ready */
                      <>
                        <Form.Item
                          name={ [fieldIndex, 'dataTarget', 'settings', 'fieldName'] }
                          style={ { marginBottom: 0 } }
                        >
                          <Select
                            filterOption={ filterByLabel }
                            options={ attributeOptions }
                            placeholder={ t('data-importer.mapping.item.destination-placeholder') }
                            showSearch
                          />
                        </Form.Item>

                        {/* Language selector — only when the selected field is localized */}
                        { isLocalized && (
                        <Form.Item
                          name={ [fieldIndex, 'dataTarget', 'settings', 'language'] }
                          style={ { marginBottom: 0 } }
                        >
                          <Select
                            filterOption={ filterByLabel }
                            options={ languageOptions }
                            placeholder={ t('data-importer.mapping.item.data-target.language-placeholder') }
                            showSearch
                          />
                        </Form.Item>
                        ) }
                      </>
                      )
                }
              </div>
            </div>
          </div>
        </Panel>
      </DndClassDiv>
      { advancedOpen && (
        <AdvancedMappingModal
          attributesMap={ attributesMap }
          baseConfig={ {
            loaderConfig: (form.getFieldsValue(true) as DataImporterFormValues).loaderConfig,
            interpreterConfig: (form.getFieldsValue(true) as DataImporterFormValues).interpreterConfig,
            resolverConfig: (form.getFieldsValue(true) as DataImporterFormValues).resolverConfig,
            processingConfig: (form.getFieldsValue(true) as DataImporterFormValues).processingConfig
          } }
          classId={ classId }
          columnHeaderOptions={ columnHeaderOptions }
          configName={ configName }
          item={ (() => {
            const index = getCurrentIndexByMappingId()
            if (index < 0) return {}
            return (form.getFieldValue(['mappingConfig', index]) as MappingConfigItem | undefined) ?? {}
          })() }
          onClose={ () => { setAdvancedOpen(false) } }
          onSave={ (updated) => {
            const index = getCurrentIndexByMappingId()
            if (index < 0) {
              setAdvancedOpen(false)
              return
            }

            form.setFieldValue(['mappingConfig', index], updated, { triggerChange: true })
            setAdvancedOpen(false)
          } }
          open={ advancedOpen }
        />
      ) }
    </Droppable>
  )
})

MappingItem.displayName = 'MappingItem'

// ── MappingItemWithFilter ──────────────────────────────────────────────────
// Each instance resolves its item by stable mappingId to avoid index-shift
// glitches after inserts/removals in Form.List.
// Also renders the MappingDropZone above itself and handles its own visibility.

export interface MappingItemWithFilterProps {
  fieldIndex: number
  mappingId: string
  insertIndex: number
  remove: (index: number) => void
  onRemoveItem: (index: number) => void
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
  classId: string | undefined
  /** Whether this item's panel is expanded (controlled) */
  expanded: boolean
  /** Called when the user clicks the panel header to toggle collapse */
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
      {/* Drop zone before this item */}
      <MappingDropZone
        add={ add }
        acceptedDataIndex={ acceptedDataIndex }
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
          mappingId={ mappingId }
          itemLabel={ item.label }
          onToggle={ onToggle }
          selectedFieldName={ item.dataTarget?.settings?.fieldName }
          transformationResultType={ item.transformationResultType }
        />
      </div>
    </div>
  )
})

MappingItemWithFilter.displayName = 'MappingItemWithFilter'
