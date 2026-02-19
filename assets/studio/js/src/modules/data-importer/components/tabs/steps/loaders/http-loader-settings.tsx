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
import { Form, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const HttpLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  const schemaOptions = [
    { value: 'https', label: 'HTTPS' },
    { value: 'http', label: 'HTTP' }
  ]

  return (
    <>
      <Form.Item
        label={ t('data-importer.loader.http.schema') }
        name={ ['loaderConfig', 'settings', 'schema'] }
        required
      >
        <Select options={ schemaOptions } />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.http.url') }
        name={ ['loaderConfig', 'settings', 'url'] }
        required
      >
        <Input placeholder="example.com/data.csv" />
      </Form.Item>
    </>
  )
}
