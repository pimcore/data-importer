/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Form, Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { filterByLabel } from '../../../../utils/select-utils'
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel'
import React, { useMemo } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract'
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen'
import { parseClassAttribute } from '../../../../components/tabs/steps/mapping-step/hooks/use-mapping-step-loader.types'

export function AttributeLoadingStrategyResolverSettings ({
  columnHeaderOptions,
  languageOptions = []
}: DynamicTypeResolverRenderProps): React.JSX.Element {
  const { t } = useTranslation()

  const dataObjectClassId = Form.useWatch(['resolverConfig', 'dataObjectClassId']) as string | undefined
  const loadingAttributeName = Form.useWatch(['resolverConfig', 'loadingStrategy', 'settings', 'attributeName']) as string | undefined

  const { data, isLoading } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId: dataObjectClassId ?? '' },
    { skip: dataObjectClassId === undefined || dataObjectClassId === '' }
  )
  const loadingAttributes = useMemo(() => (data?.attributes ?? []).map(parseClassAttribute), [data])
  const loadingAttributeOptions = useMemo(
    () => loadingAttributes.map((a) => ({ value: a.key, label: a.title })),
    [loadingAttributes]
  )
  const loadingAttrIsLocalized = useMemo(
    () => loadingAttributes.find((a) => a.key === loadingAttributeName)?.localized ?? false,
    [loadingAttributes, loadingAttributeName]
  )

  return (
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
          loadingSkeleton={ isLoading }
          options={ loadingAttributeOptions }
          placeholder={ t('data-importer.resolver.loading-strategy.attribute-name-placeholder') }
          showSearch
        />
      </Form.Item>
      {loadingAttrIsLocalized && (
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
      )}
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
  )
}
