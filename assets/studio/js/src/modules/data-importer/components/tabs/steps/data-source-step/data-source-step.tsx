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
import { Select, Form } from '@pimcore/studio-ui-bundle/components'
import { serviceIds, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { StepHeading } from '../step-heading/step-heading'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../select-utils'
import { AssetLoaderSettings } from '../loaders/asset-loader-settings'
import { UploadLoaderSettings } from '../loaders/upload-loader-settings'
import { HttpLoaderSettings } from '../loaders/http-loader-settings'
import { SftpLoaderSettings } from '../loaders/sftp-loader-settings'
import { PushLoaderSettings } from '../loaders/push-loader-settings'
import { SqlLoaderSettings } from '../loaders/sql-loader-settings'
import type { DataImporterFormValues } from '../../../../types'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { container } from "@pimcore/studio-ui-bundle";
import { DynamicTypeInterpreterRegistry } from "../../../../dynamic-types/interpreter/dynamic-type-interpreter-registry";

export interface DataSourceStepProps {
  configName: string
}

export const DataSourceStep = ({ configName }: DataSourceStepProps): React.JSX.Element => {
  const { t } = useTranslation()

    const interpreterRegistry = useMemo(
      () => container.get<DynamicTypeInterpreterRegistry>(serviceIds['DynamicTypes/TransformationDynamicTypeRegistry']),
      []
  )

  const loaderTypes = [
    { value: 'asset', label: t('data-importer.loader.asset') },
    { value: 'upload', label: t('data-importer.loader.upload') },
    { value: 'http', label: t('data-importer.loader.http') },
    { value: 'sftp', label: t('data-importer.loader.sftp') },
    { value: 'push', label: t('data-importer.loader.push') },
    { value: 'sql', label: t('data-importer.loader.sql') }
  ]

  const interpreterTypes = useMemo(
      () => interpreterRegistry.getAllTypes().map(({id, label}) => ({value: id, label})),
      [interpreterRegistry]
  )

  return (
    <FieldWidthProvider fieldWidthValues={ { medium: 900 } }>
      <StepHeading>{ t('data-importer.data-source.title') }</StepHeading>

      <DataImporterPanel>
        <Form.Item
          label={ t('data-importer.data-source.type-label') }
          name={ ['loaderConfig', 'type'] }
          required
        >
          <Select
            filterOption={ filterByLabel }
            options={ loaderTypes }
            showSearch
          />
        </Form.Item>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'asset' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.asset') }
          >
            <AssetLoaderSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'upload' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.upload') }
          >
            <UploadLoaderSettings configName={ configName } />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'http' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.http') }
          >
            <HttpLoaderSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'sftp' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.sftp') }
          >
            <SftpLoaderSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'push' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.push') }
          >
            <PushLoaderSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'sql' }>
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.loader.sql') }
          >
            <SqlLoaderSettings />
          </DataImporterPanel>
        </Form.Conditional>
      </DataImporterPanel>

      <DataImporterPanel title={ t('data-importer.file-format.title') }>
        <Form.Item
          label={ t('data-importer.file-format.title') }
          name={ ['interpreterConfig', 'type'] }
          required
        >
          <Select
            filterOption={ filterByLabel }
            options={ interpreterTypes }
            showSearch
          />
        </Form.Item>

        {interpreterRegistry.getAllTypes().map(({ id, label, renderSettings }) =>
            <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === id }>
              <DataImporterPanel border theme="fieldset" title={label}>
                {renderSettings()}
              </DataImporterPanel>
            </Form.Conditional>)}

      </DataImporterPanel>
    </FieldWidthProvider>
  )
}
