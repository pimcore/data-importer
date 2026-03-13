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

function isMappingDebugEnabled (): boolean {
  return (globalThis as any).__DI_MAPPING_DEBUG__ === true
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
  language: string | undefined
  attributesMap: Record<string, ClassAttribute[]>
}

const MappingItemComponent = ({
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
  language,
  attributesMap
}: MappingItemProps): React.JSX.Element => {
  const renderCountRef = React.useRef(0)
  renderCountRef.current += 1

  if (isMappingDebugEnabled() && (renderCountRef.current === 1 || renderCountRef.current % 50 === 0)) {
    console.debug('[DI][MappingItem] render', {
      fieldIndex,
      mappingId,
      renderCount: renderCountRef.current,
      expanded,
      hasDataSource: (dataSourceIndex ?? []).length > 0,
      trt: transformationResultType ?? 'default'
    })
  }

  const { t } = useTranslation()
  const { styles } = useStyles()
  const form = Form.useFormInstance()
  const [advancedOpen, setAdvancedOpen] = useState(false)
  const settings = useSettings()
  const languageOptions = useMemo(
    () => (settings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
    [settings.validLanguages]
  )

  const sourceCount = (dataSourceIndex ?? []).length
  const hasSource = sourceCount > 0
  const hasDestination = selectedFieldName !== undefined && selectedFieldName !== ''
  const isAdvanced = transformationResultType !== undefined &&
    transformationResultType !== '' &&
    transformationResultType !== 'default'

  const isWarningState = hasSource && !hasDestination
  const isInProgressState = sourceCount > 1 && !hasDestination

  const getCurrentIndexByMappingId = useCallback((): number => {
    return findMappingIndexById(form, mappingId)
  }, [form, mappingId])

  useEffect(() => {
    if (!expanded) return

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
  }, [expanded, columnHeaderOptions, form, getCurrentIndexByMappingId])

  const attrMapKey = resolveAttrMapKey(transformationResultType)
  const attributes: ClassAttribute[] = attributesMap[attrMapKey] ?? []

  // Only compute props needed by MappingItemContent when the panel is expanded.
  // When collapsed, MappingItemContent is not mounted so this work is skipped.
  const attributeOptions = useMemo(() => {
    if (!expanded) return []
    return attributes.map((a) => ({ value: a.key, label: a.title }))
  }, [expanded, attributes])

  const selectedAttr = useMemo(() => {
    if (!expanded) return undefined
    return attributes.find((a) => a.key === selectedFieldName)
  }, [expanded, attributes, selectedFieldName])
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
      const newSources = [...current, droppedDataIndex]
      form.setFieldValue(['mappingConfig', index, 'dataSourceIndex'], newSources, { triggerChange: true })
      // When transitioning to multi-source, reset the transformation type and destination
      // so the item correctly shows "Requires advanced setup" instead of a stale destination.
      if (newSources.length > 1) {
        form.setFieldValue(['mappingConfig', index, 'transformationResultType'], 'default')
        form.setFieldValue(['mappingConfig', index, 'dataTarget', 'settings', 'fieldName'], undefined)
        form.setFieldValue(['mappingConfig', index, 'dataTarget', 'settings', 'language'], undefined)
      }
    }
  }, [form, getCurrentIndexByMappingId])

  const handleOpenAdvanced = useCallback((): void => { setAdvancedOpen(true) }, [])
  const handleRemove = useCallback((): void => { onRemoveItem(fieldIndex) }, [onRemoveItem, fieldIndex])

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
          onChange={ onToggle }
          theme="default"
          title={ panelTitle }
        >
          { expanded && (
            <MappingItemContent
              attributeOptions={ attributeOptions }
              columnHeaderOptions={ columnHeaderOptions }
              dataSourceIndex={ dataSourceIndex }
              fieldIndex={ fieldIndex }
              isAdvanced={ isAdvanced }
              isInProgressState={ isInProgressState }
              isLocalized={ isLocalized }
              isWarningState={ isWarningState }
              itemLabel={ itemLabel }
              language={ language }
              languageOptions={ languageOptions }
              onOpenAdvanced={ handleOpenAdvanced }
              onRemove={ handleRemove }
              selectedAttr={ selectedAttr }
              selectedFieldName={ selectedFieldName }
            />
          ) }
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
}

function areMappingItemPropsEqual (prev: MappingItemProps, next: MappingItemProps): boolean {
  const bothCollapsed = !prev.expanded && !next.expanded

  if (bothCollapsed) {
    return (
      prev.expanded === next.expanded &&
      prev.fieldIndex === next.fieldIndex &&
      prev.mappingId === next.mappingId &&
      prev.itemLabel === next.itemLabel &&
      prev.onToggle === next.onToggle &&
      prev.onRemoveItem === next.onRemoveItem &&
      prev.remove === next.remove
    )
  }

  const prevSrc = prev.dataSourceIndex
  const nextSrc = next.dataSourceIndex
  const dataSourceIndexEqual =
    prevSrc === nextSrc ||
    (prevSrc !== undefined &&
      nextSrc !== undefined &&
      prevSrc.length === nextSrc.length &&
      prevSrc.every((v, i) => v === nextSrc[i]))

  return (
    prev.fieldIndex === next.fieldIndex &&
    prev.mappingId === next.mappingId &&
    prev.remove === next.remove &&
    prev.onRemoveItem === next.onRemoveItem &&
    prev.configName === next.configName &&
    prev.columnHeaderOptions === next.columnHeaderOptions &&
    prev.classId === next.classId &&
    prev.expanded === next.expanded &&
    prev.onToggle === next.onToggle &&
    prev.itemLabel === next.itemLabel &&
    dataSourceIndexEqual &&
    prev.transformationResultType === next.transformationResultType &&
    prev.selectedFieldName === next.selectedFieldName &&
    prev.language === next.language &&
    prev.attributesMap === next.attributesMap
  )
}

export const MappingItem = React.memo(MappingItemComponent, areMappingItemPropsEqual)

MappingItem.displayName = 'MappingItem'
