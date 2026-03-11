/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { Select, Form, Switch } from '@pimcore/studio-ui-bundle/components'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { useClassDefinitionCollectionQuery } from '@pimcore/studio-ui-bundle/api/class-definition'
import { TransformerSettingsLayout } from '../transformer-settings-layout'
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../data-importer-api-slice.gen'

const SYSTEM_ATTRIBUTES = ['id', 'path', 'key']

interface LoadDataObjectTransformerConfig {
  loadStrategy?: string
  attributeDataObjectClassId?: string
  attributeName?: string
  partialMatch?: boolean
  attributeLanguage?: string
  loadUnpublished?: boolean
}

interface LoadDataObjectTransformerFormProps {
  settings: LoadDataObjectTransformerConfig
  onChange: (settings: LoadDataObjectTransformerConfig) => void
}

export const LoadDataObjectTransformerForm = ({ settings, onChange }: LoadDataObjectTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  const appSettings = useSettings()
  const languageOptions = useMemo(
    () => (appSettings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
    [appSettings.validLanguages]
  )

  const { data: classDefinitions, isLoading: isLoadingClasses } = useClassDefinitionCollectionQuery()
  const classOptions = (classDefinitions?.items ?? []).map((cls) => ({
    value: cls.id,
    label: cls.name
  }))

  const loadStrategy: string = settings.loadStrategy ?? 'id'
  const isAttribute = loadStrategy === 'attribute'
  const classId: string = settings.attributeDataObjectClassId ?? ''
  const attributeName: string | undefined = settings.attributeName

  const { data: attrData, isLoading: isLoadingAttributes } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId, systemRead: true },
    { skip: classId === '' || !isAttribute }
  )
  const attributes = useMemo(
    () => (attrData?.attributes ?? []).map((raw: object) => {
      const obj = raw as Record<string, any>
      return {
        key: (obj.key ?? obj.name ?? '') as string,
        title: (obj.title ?? obj.name ?? obj.key ?? '') as string,
        localized: Boolean(obj.localized ?? false)
      }
    }),
    [attrData]
  )
  const attributeOptions = attributes.map((a) => ({ value: a.key, label: a.title }))
  const selectedAttr = attributes.find((a) => a.key === attributeName)
  const isLocalized = selectedAttr?.localized ?? false
  const showPartialMatch = isAttribute &&
    attributeName !== undefined &&
    attributeName !== null &&
    attributeName !== '' &&
    !SYSTEM_ATTRIBUTES.includes(attributeName)

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Load strategy</span> }
          >
            <Select
              onChange={ (v) => {
                update('loadStrategy', v)
                if (v !== 'attribute') {
                  onChange({ ...settings, loadStrategy: v, attributeDataObjectClassId: undefined, attributeName: undefined, loadUnpublished: undefined })
                }
              } }
              options={ [
                { value: 'id', label: 'By ID' },
                { value: 'path', label: 'By Path' },
                { value: 'attribute', label: 'By Attribute' }
              ] }
              value={ loadStrategy }
            />
          </Form.Item>

          { isAttribute && (
            <>
              <Form.Item
                className={ styles.formItem }
                label={ <span className={ styles.label }>Class</span> }
              >
                <Select
                  loadingSkeleton={ isLoadingClasses }
                  onChange={ (v) => {
                    onChange({ ...settings, attributeDataObjectClassId: v, attributeName: undefined })
                  } }
                  options={ classOptions }
                  value={ classId !== '' ? classId : undefined }
                />
              </Form.Item>

              <Form.Item
                className={ styles.formItem }
                label={ <span className={ styles.label }>Attribute name</span> }
              >
                <Select
                  loadingSkeleton={ isLoadingAttributes }
                  onChange={ (v) => { update('attributeName', v) } }
                  options={ attributeOptions }
                  value={ attributeName }
                />
              </Form.Item>

              { showPartialMatch && (
                <Form.Item className={ styles.formItemSwitch }>
                  <Switch
                    checked={ Boolean(settings.partialMatch) }
                    labelRight="Accept partial match"
                    onChange={ (v) => { update('partialMatch', v) } }
                    size="small"
                  />
                </Form.Item>
              ) }

              { isLocalized && (
                <Form.Item
                  className={ styles.formItem }
                  label={ <span className={ styles.label }>Language</span> }
                >
                  <Select
                    onChange={ (v) => { update('attributeLanguage', v) } }
                    options={ languageOptions }
                    value={ settings.attributeLanguage }
                  />
                </Form.Item>
              ) }

              <Form.Item className={ styles.formItemLast }>
                <Switch
                  checked={ Boolean(settings.loadUnpublished) }
                  labelRight="Load unpublished"
                  onChange={ (v) => { update('loadUnpublished', v) } }
                  size="small"
                />
              </Form.Item>
            </>
          ) }

          { !isAttribute && <div style={ { height: 0 } } /> }
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
