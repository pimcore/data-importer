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
import { Select, Form } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { StepHeading } from '../step-heading/step-heading'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../select-utils'
import { AssetLoaderSettings } from '../loaders/asset-loader-settings'
import { UploadLoaderSettings } from '../loaders/upload-loader-settings'
import { HttpLoaderSettings } from '../loaders/http-loader-settings'
import { SftpLoaderSettings } from '../loaders/sftp-loader-settings'
import { PushLoaderSettings } from '../loaders/push-loader-settings'
import { SqlLoaderSettings } from '../loaders/sql-loader-settings'
import { CsvInterpreterSettings } from '../interpreters/csv-interpreter-settings'
import { JsonInterpreterSettings } from '../interpreters/json-interpreter-settings'
import { XmlInterpreterSettings } from '../interpreters/xml-interpreter-settings'
import { XlsxInterpreterSettings } from '../interpreters/xlsx-interpreter-settings'
import { SqlInterpreterSettings } from '../interpreters/sql-interpreter-settings'
import type { DataImporterFormValues } from '../../../../types'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'

export interface DataSourceStepProps {
  configName: string
}

export const DataSourceStep = ({ configName }: DataSourceStepProps): React.JSX.Element => {
  const { t } = useTranslation()

  const loaderTypes = [
    { value: 'asset', label: t('data-importer.loader.asset') },
    { value: 'upload', label: t('data-importer.loader.upload') },
    { value: 'http', label: t('data-importer.loader.http') },
    { value: 'sftp', label: t('data-importer.loader.sftp') },
    { value: 'push', label: t('data-importer.loader.push') },
    { value: 'sql', label: t('data-importer.loader.sql') }
  ]

  const interpreterTypes = [
    { value: 'csv', label: t('data-importer.interpreter.csv') },
    { value: 'json', label: t('data-importer.interpreter.json') },
    { value: 'xml', label: t('data-importer.interpreter.xml') },
    { value: 'xlsx', label: t('data-importer.interpreter.xlsx') },
    { value: 'sql', label: t('data-importer.interpreter.sql') }
  ]

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

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'csv' }>
          <DataImporterPanel
            border
            theme="fieldset"
            title={ t('data-importer.interpreter.csv') }
          >
            <CsvInterpreterSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'json' }>
          <DataImporterPanel
            border
            theme="fieldset"
            title={ t('data-importer.interpreter.json') }
          >
            <JsonInterpreterSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'xml' }>
          <DataImporterPanel
            border
            theme="fieldset"
            title={ t('data-importer.interpreter.xml') }
          >
            <XmlInterpreterSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'xlsx' }>
          <DataImporterPanel
            border
            theme="fieldset"
            title={ t('data-importer.interpreter.xlsx') }
          >
            <XlsxInterpreterSettings />
          </DataImporterPanel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'sql' }>
          <DataImporterPanel
            border
            theme="fieldset"
            title={ t('data-importer.interpreter.sql') }
          >
            <SqlInterpreterSettings />
          </DataImporterPanel>
        </Form.Conditional>
      </DataImporterPanel>
    </FieldWidthProvider>
  )
}
