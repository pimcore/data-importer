/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo, useState } from 'react'
import { Form, IconButton, IconTextButton, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ClassAttribute } from '../../../../../types'
import { filterByLabel } from '../../../../../utils/select-utils'
import { useStyles } from '../mapping-step.styles'
import { ArrowColumn } from './arrow-column/arrow-column'

function isMappingDebugEnabled (): boolean {
  return (globalThis as any).__DI_MAPPING_DEBUG__ === true
}

export interface MappingItemContentProps {
  fieldIndex: number
  itemLabel: string | undefined
  dataSourceIndex: string[] | undefined
  isAdvanced: boolean
  isInProgressState: boolean
  isWarningState: boolean
  selectedAttr: ClassAttribute | undefined
  selectedFieldName: string | undefined
  isLocalized: boolean
  language: string | undefined
  columnHeaderOptions: Array<{ value: string, label: string }>
  attributeOptions: Array<{ value: string, label: string }>
  languageOptions: Array<{ value: string, label: string }>
  onOpenAdvanced: () => void
  onRemove: () => void
}

export const MappingItemContent = React.memo(({
  fieldIndex,
  itemLabel,
  dataSourceIndex,
  isAdvanced,
  isInProgressState,
  isWarningState,
  selectedAttr,
  selectedFieldName,
  isLocalized,
  language,
  columnHeaderOptions,
  attributeOptions,
  languageOptions,
  onOpenAdvanced,
  onRemove
}: MappingItemContentProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const form = Form.useFormInstance()
  const [sourceOptionsReady, setSourceOptionsReady] = useState(false)
  const [destinationOptionsReady, setDestinationOptionsReady] = useState(false)

  const selectedSourceOptions = useMemo(() => {
    if ((dataSourceIndex ?? []).length === 0) return []

    const selectedSet = new Set(dataSourceIndex)
    return columnHeaderOptions.filter((opt) => selectedSet.has(opt.value))
  }, [dataSourceIndex, columnHeaderOptions])

  const sourceOptions = sourceOptionsReady ? columnHeaderOptions : selectedSourceOptions

  const selectedDestinationOption = useMemo(() => {
    if (selectedFieldName === undefined || selectedFieldName === '') return []
    return [{ value: selectedFieldName, label: selectedAttr?.title ?? selectedFieldName }]
  }, [selectedAttr, selectedFieldName])

  const destinationOptions = destinationOptionsReady ? attributeOptions : selectedDestinationOption

  useEffect(() => {
    if (!isMappingDebugEnabled()) return

    const startedAt = performance.now()
    requestAnimationFrame(() => {
      console.debug('[DI][Perf] mapping item content painted', {
        fieldIndex,
        expandedState: 'expanded',
        durationMs: Number((performance.now() - startedAt).toFixed(2))
      })
    })
  }, [fieldIndex])

  return (
    <div className={ styles.mappingItemContent }>
      <div className={ styles.mappingLabelRow }>
        <div
          className={ styles.mappingLabelInput }
          style={ { flex: 1 } }
        >
          <Input
            onChange={ (e) => {
              form.setFieldValue(['mappingConfig', fieldIndex, 'label'], e.target.value, { triggerChange: true })
            } }
            placeholder={ t('data-importer.mapping.item.label') }
            value={ itemLabel ?? '' }
          />
        </div>

        <IconTextButton
          icon={ { value: 'transformation' } }
          onClick={ onOpenAdvanced }
          type="default"
        >
          { t('data-importer.mapping.item.advanced') }
        </IconTextButton>

        <IconButton
          icon={ { value: 'trash' } }
          onClick={ onRemove }
          tooltip={ { title: t('data-importer.mapping.item.delete') } }
          type="default"
        />
      </div>

      <div className={ styles.mappingDivider } />

      <div className={ styles.sourcesDestRow }>
        <div className={ styles.sourcesDestCol }>
          <div>
            { t('data-importer.mapping.item.source') }
          </div>
          <div className={ styles.sourceDropZone }>
            <Select
              filterOption={ filterByLabel }
              mode="multiple"
              onChange={ (value: string[]) => {
                form.setFieldValue(['mappingConfig', fieldIndex, 'dataSourceIndex'], value, { triggerChange: true })
                // When transitioning to multi-source, reset the transformation type and destination
                // so the item correctly shows "Requires advanced setup" instead of a stale destination.
                if (value.length > 1) {
                  form.setFieldValue(['mappingConfig', fieldIndex, 'transformationResultType'], 'default')
                  form.setFieldValue(['mappingConfig', fieldIndex, 'dataTarget', 'settings', 'fieldName'], undefined)
                  form.setFieldValue(['mappingConfig', fieldIndex, 'dataTarget', 'settings', 'language'], undefined)
                }
              } }
              onFocus={ () => { setSourceOptionsReady(true) } }
              options={ sourceOptions }
              placeholder={ t('data-importer.mapping.item.source-placeholder') }
              showSearch
              style={ { maxWidth: '100%' } }
              value={ dataSourceIndex ?? [] }
            />
          </div>
        </div>

        <ArrowColumn
          isAdvanced={ isAdvanced }
          isInProgressState={ isInProgressState }
          isWarningState={ isWarningState }
        />

        <div className={ styles.sourcesDestCol }>
          <div>
            { t('data-importer.mapping.item.destination') }
          </div>

          { isAdvanced && (
            <div className={ styles.destinationTextBlock }>
              <span>{ selectedAttr?.title ?? selectedFieldName ?? '' }</span>
            </div>
          ) }

          { !isAdvanced && isInProgressState && (
            <div className={ styles.requiresAdvancedHint }>
              { t('data-importer.mapping.item.requires-advanced-setup') }
            </div>
          ) }

          { !isAdvanced && !isInProgressState && (
            <>
              <Select
                filterOption={ filterByLabel }
                onChange={ (value: string) => {
                  form.setFieldValue(['mappingConfig', fieldIndex, 'dataTarget', 'settings', 'fieldName'], value, { triggerChange: true })
                } }
                onFocus={ () => { setDestinationOptionsReady(true) } }
                options={ destinationOptions }
                placeholder={ t('data-importer.mapping.item.destination-placeholder') }
                showSearch
                style={ { maxWidth: '100%' } }
                value={ selectedFieldName ?? undefined }
              />

              { isLocalized && (
                <Select
                  filterOption={ filterByLabel }
                  onChange={ (value: string) => {
                    form.setFieldValue(['mappingConfig', fieldIndex, 'dataTarget', 'settings', 'language'], value, { triggerChange: true })
                  } }
                  options={ languageOptions }
                  placeholder={ t('data-importer.mapping.item.data-target.language-placeholder') }
                  showSearch
                  style={ { maxWidth: '100%' } }
                  value={ language ?? undefined }
                />
              ) }
            </>
          ) }
        </div>
      </div>
    </div>
  )
})

MappingItemContent.displayName = 'MappingItemContent'
