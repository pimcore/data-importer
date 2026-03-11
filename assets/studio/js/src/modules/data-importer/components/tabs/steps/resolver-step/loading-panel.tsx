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
import { Form, Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../select-utils'

export interface LoadingPanelProps {
  loadingStrategyType: string | undefined
  loadingStrategyOptions: Array<{ value: string, label: string }>
  columnHeaderOptions: Array<{ value: string, label: string }>
  isLoadingLoadingAttrs: boolean
  loadingAttributeOptions: Array<{ value: string, label: string }>
  loadingAttrIsLocalized: boolean
  languageOptions: Array<{ value: string, label: string }>
}

export const LoadingPanel = ({
  loadingStrategyType,
  loadingStrategyOptions,
  columnHeaderOptions,
  isLoadingLoadingAttrs,
  loadingAttributeOptions,
  loadingAttrIsLocalized,
  languageOptions
}: LoadingPanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <DataImporterPanel title={ t('data-importer.resolver.element-loading') }>
      <Form.Item
        label={ t('data-importer.resolver.loading-strategy') }
        name={ ['resolverConfig', 'loadingStrategy', 'type'] }
        tooltip={ t('data-importer.resolver.loading-strategy.tooltip') }
      >
        <Select
          filterOption={ filterByLabel }
          options={ loadingStrategyOptions }
          showSearch
        />
      </Form.Item>

      { loadingStrategyType === 'id' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.loading-strategy.id') }
        >
          <Form.Item
            label={ t('data-importer.resolver.loading-strategy.data-source-index') }
            name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
        </DataImporterPanel>
      ) }

      { loadingStrategyType === 'path' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.loading-strategy.path') }
        >
          <Form.Item
            label={ t('data-importer.resolver.loading-strategy.data-source-index') }
            name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
        </DataImporterPanel>
      ) }

      { loadingStrategyType === 'attribute' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.loading-strategy.attribute') }
        >
          <Form.Item
            label={ t('data-importer.resolver.loading-strategy.data-source-index') }
            name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
          <Form.Item
            label={ t('data-importer.resolver.loading-strategy.attribute-name') }
            name={ ['resolverConfig', 'loadingStrategy', 'settings', 'attributeName'] }
          >
            <Select
              filterOption={ filterByLabel }
              loadingSkeleton={ isLoadingLoadingAttrs }
              options={ loadingAttributeOptions }
              placeholder={ t('data-importer.resolver.loading-strategy.attribute-name-placeholder') }
              showSearch
            />
          </Form.Item>
          { loadingAttrIsLocalized && (
            <Form.Item
              label={ t('data-importer.resolver.loading-strategy.language') }
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'language'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ languageOptions }
                placeholder={ t('data-importer.resolver.loading-strategy.language-placeholder') }
                showSearch
              />
            </Form.Item>
          ) }
          <Form.Item
            name={ ['resolverConfig', 'loadingStrategy', 'settings', 'includeUnpublished'] }
            valuePropName="checked"
          >
            <Switch
              labelRight={ t('data-importer.resolver.loading-strategy.include-unpublished') }
              size="small"
            />
          </Form.Item>
        </DataImporterPanel>
      ) }
    </DataImporterPanel>
  )
}
