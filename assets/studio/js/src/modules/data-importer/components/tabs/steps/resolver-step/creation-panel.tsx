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
import { Form, Input, ManyToOneRelationPath, Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../select-utils'

export interface CreationPanelProps {
  createLocationType: string | undefined
  createLocationStrategyOptions: Array<{ value: string, label: string }>
  columnHeaderOptions: Array<{ value: string, label: string }>
  createFindStrategy: string | undefined
  findStrategyOptions: Array<{ value: string, label: string }>
  classOptions: Array<{ value: string, label: string }>
  isLoadingClasses: boolean
  isLoadingCreateFindParentAttrs: boolean
  createFindParentAttrOptions: Array<{ value: string, label: string }>
  createFindParentAttrIsLocalized: boolean
  languageOptions: Array<{ value: string, label: string }>
}

export const CreationPanel = ({
  createLocationType,
  createLocationStrategyOptions,
  columnHeaderOptions,
  createFindStrategy,
  findStrategyOptions,
  classOptions,
  isLoadingClasses,
  isLoadingCreateFindParentAttrs,
  createFindParentAttrOptions,
  createFindParentAttrIsLocalized,
  languageOptions
}: CreationPanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <DataImporterPanel title={ t('data-importer.resolver.element-creation') }>
      <Form.Item
        label={ t('data-importer.resolver.create-location-strategy') }
        name={ ['resolverConfig', 'createLocationStrategy', 'type'] }
        tooltip={ t('data-importer.resolver.create-location-strategy.tooltip') }
      >
        <Select
          filterOption={ filterByLabel }
          options={ createLocationStrategyOptions }
          showSearch
        />
      </Form.Item>

      { createLocationType === 'staticPath' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.staticPath') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.path') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'path'] }
            required
            rules={ [{ required: true, message: t('data-importer.validation.required', { field: t('data-importer.resolver.location-strategy.path') }) }] }
          >
            <ManyToOneRelationPath
              allowPathTextInput
              allowedDataObjectTypes={ ['folder'] }
              dataObjectsAllowed
            />
          </Form.Item>
        </DataImporterPanel>
      ) }

      { createLocationType === 'findOrCreateFolder' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.findOrCreateFolder') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.data-source-index') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.fallback-path') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'fallbackPath'] }
            tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
          >
            <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
          </Form.Item>
        </DataImporterPanel>
      ) }

      { createLocationType === 'findParent' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.findParent') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.find-strategy') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'findStrategy'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ findStrategyOptions }
              showSearch
            />
          </Form.Item>
          { createFindStrategy === 'attribute' && (
            <>
              <Form.Item
                label={ t('data-importer.resolver.location-strategy.attribute-class') }
                name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeDataObjectClassId'] }
              >
                <Select
                  filterOption={ filterByLabel }
                  loadingSkeleton={ isLoadingClasses }
                  options={ classOptions }
                  showSearch
                />
              </Form.Item>
              <Form.Item
                label={ t('data-importer.resolver.location-strategy.attribute-name') }
                name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeName'] }
              >
                <Select
                  filterOption={ filterByLabel }
                  loadingSkeleton={ isLoadingCreateFindParentAttrs }
                  options={ createFindParentAttrOptions }
                  showSearch
                />
              </Form.Item>
              { createFindParentAttrIsLocalized && (
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-language') }
                  name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeLanguage'] }
                >
                  <Select
                    filterOption={ filterByLabel }
                    options={ languageOptions }
                    showSearch
                  />
                </Form.Item>
              ) }
            </>
          ) }
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.data-source-index') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.fallback-path') }
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'fallbackPath'] }
            tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
          >
            <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
          </Form.Item>
          <Form.Item
            name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'asVariant'] }
            valuePropName="checked"
          >
            <Switch
              labelRight={ t('data-importer.resolver.location-strategy.as-variant') }
              size="small"
            />
          </Form.Item>
        </DataImporterPanel>
      ) }
    </DataImporterPanel>
  )
}
