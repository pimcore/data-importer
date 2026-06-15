/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Form, Select } from '@pimcore/studio-ui-bundle/components'
import { filterByLabel } from '../../../../utils/select-utils'
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel'
import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract'

export function IdLoadingStrategyResolverSettings ({ columnHeaderOptions }: DynamicTypeResolverRenderProps): React.JSX.Element {
  const { t } = useTranslation()

  return (
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
  )
}
