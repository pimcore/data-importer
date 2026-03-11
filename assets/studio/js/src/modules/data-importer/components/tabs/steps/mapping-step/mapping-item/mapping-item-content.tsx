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
import { Form, IconButton, IconTextButton, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ClassAttribute } from '../../../../../types'
import { filterByLabel } from '../../select-utils'
import { useStyles } from '../mapping-step.styles'
import { ArrowColumn } from './arrow-column/arrow-column'

export interface MappingItemContentProps {
  fieldIndex: number
  isAdvanced: boolean
  isInProgressState: boolean
  isWarningState: boolean
  selectedAttr: ClassAttribute | undefined
  selectedFieldName: string | undefined
  isLocalized: boolean
  columnHeaderOptions: Array<{ value: string, label: string }>
  attributeOptions: Array<{ value: string, label: string }>
  languageOptions: Array<{ value: string, label: string }>
  onOpenAdvanced: () => void
  onRemove: () => void
}

export const MappingItemContent = ({
  fieldIndex,
  isAdvanced,
  isInProgressState,
  isWarningState,
  selectedAttr,
  selectedFieldName,
  isLocalized,
  columnHeaderOptions,
  attributeOptions,
  languageOptions,
  onOpenAdvanced,
  onRemove
}: MappingItemContentProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <div className={ styles.mappingItemContent }>
      <Form.Item
        hidden
        name={ [fieldIndex, 'transformationResultType'] }
      >
        <Input />
      </Form.Item>

      <div className={ styles.mappingLabelRow }>
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

        <ArrowColumn
          isAdvanced={ isAdvanced }
          isInProgressState={ isInProgressState }
          isWarningState={ isWarningState }
        />

        <div className={ styles.sourcesDestCol }>
          <div>
            { t('data-importer.mapping.item.destination') }
          </div>

          { isAdvanced
            ? (
              <>
                <div className={ styles.destinationTextBlock }>
                  <span>{ selectedAttr?.title ?? selectedFieldName ?? '' }</span>
                </div>
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
                <>
                  <div className={ styles.requiresAdvancedHint }>
                    { t('data-importer.mapping.item.requires-advanced-setup') }
                  </div>
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
                ) }
        </div>
      </div>
    </div>
  )
}
