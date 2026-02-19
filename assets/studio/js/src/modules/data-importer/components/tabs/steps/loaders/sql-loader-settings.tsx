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
import { Form, Select, TextArea } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useBundleDataImporterConnectionListQuery } from '../../../../data-importer-api-slice-enhanced'

export const SqlLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()
  const { data: connectionsData, isLoading } = useBundleDataImporterConnectionListQuery()

  const connectionOptions = connectionsData?.connections.map(conn => ({
    value: conn.value ?? '',
    label: conn.name ?? ''
  })) ?? []

  return (
    <>
      <Form.Item
        label={ t('data-importer.loader.sql.connection') }
        name={ ['loaderConfig', 'settings', 'connection'] }
        required
      >
        <Select
          loading={ isLoading }
          options={ connectionOptions }
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sql.select') }
        name={ ['loaderConfig', 'settings', 'select'] }
        required
      >
        <TextArea
          placeholder="a, b, c"
          rows={ 4 }
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sql.from') }
        name={ ['loaderConfig', 'settings', 'from'] }
        required
      >
        <TextArea
          placeholder="table_name INNER JOIN other_table ON condition"
          rows={ 4 }
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sql.where') }
        name={ ['loaderConfig', 'settings', 'where'] }
      >
        <TextArea
          placeholder="column = 'value'"
          rows={ 4 }
        />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.loader.sql.group-by') }
        name={ ['loaderConfig', 'settings', 'groupBy'] }
      >
        <TextArea
          placeholder="column1, column2"
          rows={ 4 }
        />
      </Form.Item>
    </>
  )
}
