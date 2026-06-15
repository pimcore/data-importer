/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Form, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel'
import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { filterByLabel } from '../../../../utils/select-utils'
import { type DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract'

export function FindOrCreateFolderLocationUpdateSettings ({ columnHeaderOptions }: DynamicTypeResolverRenderProps): React.JSX.Element {
  const { t } = useTranslation()

  return (
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
  )
}
