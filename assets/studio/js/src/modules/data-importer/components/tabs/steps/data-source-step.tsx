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
import { FormKit, Select, Space, Form } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { AssetLoaderSettings } from './loaders/asset-loader-settings'
import { UploadLoaderSettings } from './loaders/upload-loader-settings'
import { HttpLoaderSettings } from './loaders/http-loader-settings'
import { SftpLoaderSettings } from './loaders/sftp-loader-settings'
import { PushLoaderSettings } from './loaders/push-loader-settings'
import { SqlLoaderSettings } from './loaders/sql-loader-settings'
import { CsvInterpreterSettings } from './interpreters/csv-interpreter-settings'
import { JsonInterpreterSettings } from './interpreters/json-interpreter-settings'
import { XmlInterpreterSettings } from './interpreters/xml-interpreter-settings'
import { XlsxInterpreterSettings } from './interpreters/xlsx-interpreter-settings'
import { SqlInterpreterSettings } from './interpreters/sql-interpreter-settings'
import type { DataImporterFormValues } from '../../../types'

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
    <Space
      direction="vertical"
      size="large"
      style={ { width: '100%' } }
    >
      <div>
        <Form.Item
          label={ t('data-importer.data-source.type') }
          name={ ['loaderConfig', 'type'] }
          required
        >
          <Select options={ loaderTypes } />
        </Form.Item>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'asset' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.asset') }
          >
            <AssetLoaderSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'upload' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.upload') }
          >
            <UploadLoaderSettings configName={ configName } />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'http' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.http') }
          >
            <HttpLoaderSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'sftp' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.sftp') }
          >
            <SftpLoaderSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'push' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.push') }
          >
            <PushLoaderSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).loaderConfig?.type === 'sql' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.loader.sql') }
          >
            <SqlLoaderSettings />
          </FormKit.Panel>
        </Form.Conditional>
      </div>

      <div>
        <Form.Item
          label={ t('data-importer.file-format.type') }
          name={ ['interpreterConfig', 'type'] }
          required
        >
          <Select options={ interpreterTypes } />
        </Form.Item>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'csv' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.interpreter.csv') }
          >
            <CsvInterpreterSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'json' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.interpreter.json') }
          >
            <JsonInterpreterSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'xml' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.interpreter.xml') }
          >
            <XmlInterpreterSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'xlsx' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.interpreter.xlsx') }
          >
            <XlsxInterpreterSettings />
          </FormKit.Panel>
        </Form.Conditional>

        <Form.Conditional condition={ (values) => (values as unknown as DataImporterFormValues).interpreterConfig?.type === 'sql' }>
          <FormKit.Panel
            theme="fieldset"
            title={ t('data-importer.interpreter.sql') }
          >
            <SqlInterpreterSettings />
          </FormKit.Panel>
        </Form.Conditional>
      </div>
    </Space>
  )
}
