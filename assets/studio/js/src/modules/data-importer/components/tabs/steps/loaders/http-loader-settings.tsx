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
import { Form, FormKit, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { filterByLabel } from '../select-utils'

export const HttpLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  const schemaOptions = [
    { value: 'https://', label: 'HTTPS' },
    { value: 'http://', label: 'HTTP' }
  ]

  return (
    <FormKit.Panel>
      <Form.Item
        label={ t('data-importer.loader.http.schema') }
        name={ ['loaderConfig', 'settings', 'schema'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.http.schema') }) }
        ] }
      >
        <Select
          filterOption={ filterByLabel }
          options={ schemaOptions }
          showSearch
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.http.url') }
        name={ ['loaderConfig', 'settings', 'url'] }
        required
        rules={ [
          { required: true, message: t('data-importer.validation.required', { field: t('data-importer.loader.http.url') }) }
        ] }
      >
        <Input placeholder="example.com/data.csv" />
      </Form.Item>
    </FormKit.Panel>
  )
}
