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
import { Form, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../select-utils'

export interface PublishingPanelProps {
  publishingStrategyType: string | undefined
  publishingStrategyOptions: Array<{ value: string, label: string }>
  columnHeaderOptions: Array<{ value: string, label: string }>
}

export const PublishingPanel = ({ publishingStrategyType, publishingStrategyOptions, columnHeaderOptions }: PublishingPanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <DataImporterPanel title={ t('data-importer.resolver.element-publishing') }>
      <Form.Item
        label={ t('data-importer.resolver.publishing-strategy') }
        name={ ['resolverConfig', 'publishingStrategy', 'type'] }
        tooltip={ t('data-importer.resolver.publishing-strategy.tooltip') }
      >
        <Select
          filterOption={ filterByLabel }
          options={ publishingStrategyOptions }
          showSearch
        />
      </Form.Item>

      { publishingStrategyType === 'attributeBased' && (
        <DataImporterPanel
          theme="fieldset"
          title={ t('data-importer.resolver.publishing-strategy.attributeBased') }
        >
          <Form.Item
            label={ t('data-importer.resolver.publishing-strategy.data-source-index') }
            name={ ['resolverConfig', 'publishingStrategy', 'settings', 'dataSourceIndex'] }
          >
            <Select
              filterOption={ filterByLabel }
              options={ columnHeaderOptions }
              placeholder={ t('data-importer.resolver.publishing-strategy.data-source-index-placeholder') }
              showSearch
            />
          </Form.Item>
        </DataImporterPanel>
      ) }
    </DataImporterPanel>
  )
}
