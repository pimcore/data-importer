/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Droppable,
  type DragAndDropInfo,
  Form,
  Panel
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { type MappingConfigItem, type ClassAttribute, resolveAttrMapKey, type DataImporterFormValues } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'
import { AdvancedMappingModal } from '../../advanced-mapping-modal'
import { DndClassDiv } from '../dnd-class-div/dnd-class-div'
import { DND_TYPE } from '../sources-panel/sources-panel'
import { findMappingIndexById } from '../utils/mapping-identity'
import { MappingItemContent } from './mapping-item-content'

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
          <MappingItemContent
            attributeOptions={ attributeOptions }
            columnHeaderOptions={ columnHeaderOptions }
            fieldIndex={ fieldIndex }
            isAdvanced={ isAdvanced }
            isInProgressState={ isInProgressState }
            isLocalized={ isLocalized }
            isWarningState={ isWarningState }
            languageOptions={ languageOptions }
            onOpenAdvanced={ () => { setAdvancedOpen(true) } }
            onRemove={ () => { onRemoveItem(fieldIndex) } }
            selectedAttr={ selectedAttr }
            selectedFieldName={ selectedFieldName }
          />
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
