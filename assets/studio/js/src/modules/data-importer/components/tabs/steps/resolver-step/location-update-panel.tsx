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

export interface LocationUpdatePanelProps {
  updateLocationType: string | undefined
  locationUpdateStrategyOptions: Array<{ value: string, label: string }>
  columnHeaderOptions: Array<{ value: string, label: string }>
  updateFindStrategy: string | undefined
  findStrategyOptions: Array<{ value: string, label: string }>
  classOptions: Array<{ value: string, label: string }>
  updateFindParentAttrOptions: Array<{ value: string, label: string }>
  updateFindParentAttrIsLocalized: boolean
  languageOptions: Array<{ value: string, label: string }>
}

export const LocationUpdatePanel = ({
  updateLocationType,
  locationUpdateStrategyOptions,
  columnHeaderOptions,
  updateFindStrategy,
  findStrategyOptions,
  classOptions,
  updateFindParentAttrOptions,
  updateFindParentAttrIsLocalized,
  languageOptions
}: LocationUpdatePanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <DataImporterPanel title={ t('data-importer.resolver.element-location-update') }>
      <Form.Item
        label={ t('data-importer.resolver.location-update-strategy') }
        name={ ['resolverConfig', 'locationUpdateStrategy', 'type'] }
        tooltip={ t('data-importer.resolver.location-update-strategy.tooltip') }
      >
        <Select
          filterOption={ filterByLabel }
          options={ locationUpdateStrategyOptions }
          showSearch
        />
      </Form.Item>

      { updateLocationType === 'staticPath' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.staticPath') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.path') }
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'path'] }
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

      { updateLocationType === 'findOrCreateFolder' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.findOrCreateFolder') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.data-source-index') }
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'dataSourceIndex'] }
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
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'fallbackPath'] }
            tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
          >
            <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
          </Form.Item>
        </DataImporterPanel>
      ) }

      { updateLocationType === 'findParent' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.location-strategy.findParent') }
        >
          <Form.Item
            label={ t('data-importer.resolver.location-strategy.find-strategy') }
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'findStrategy'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ findStrategyOptions }
              showSearch
            />
          </Form.Item>
          { updateFindStrategy === 'attribute' && (
            <>
              <Form.Item
                label={ t('data-importer.resolver.location-strategy.attribute-class') }
                name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeDataObjectClassId'] }
              >
                <Select
                  filterOption={ filterByLabel }
                  options={ classOptions }
                  showSearch
                />
              </Form.Item>
              <Form.Item
                label={ t('data-importer.resolver.location-strategy.attribute-name') }
                name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeName'] }
              >
                <Select
                  filterOption={ filterByLabel }
                  options={ updateFindParentAttrOptions }
                  showSearch
                />
              </Form.Item>
              { updateFindParentAttrIsLocalized && (
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-language') }
                  name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeLanguage'] }
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
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'dataSourceIndex'] }
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
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'fallbackPath'] }
            tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
          >
            <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
          </Form.Item>
          <Form.Item
            name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'asVariant'] }
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
