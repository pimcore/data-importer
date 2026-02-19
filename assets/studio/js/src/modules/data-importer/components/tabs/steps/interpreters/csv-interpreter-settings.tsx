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
import { Form, Input, Checkbox } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const CsvInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <>
      <Form.Item
        label={ t('data-importer.interpreter.csv.skip-first-row') }
        name={ ['interpreterConfig', 'settings', 'skipFirstRow'] }
        valuePropName="checked"
      >
        <Checkbox />
      </Form.Item>

      <Form.Item
        label={ t('data-importer.interpreter.csv.save-header-name') }
        name={ ['interpreterConfig', 'settings', 'saveHeaderName'] }
        valuePropName="checked"
      >
        <Checkbox />
      </Form.Item>

      <Form.Item
        initialValue=","
        label={ t('data-importer.interpreter.csv.delimiter') }
        name={ ['interpreterConfig', 'settings', 'delimiter'] }
        required
      >
        <Input style={ { width: '100px' } } />
      </Form.Item>

      <Form.Item
        initialValue='"'
        label={ t('data-importer.interpreter.csv.enclosure') }
        name={ ['interpreterConfig', 'settings', 'enclosure'] }
        required
      >
        <Input style={ { width: '100px' } } />
      </Form.Item>

      <Form.Item
        initialValue="\\"
        label={ t('data-importer.interpreter.csv.escape') }
        name={ ['interpreterConfig', 'settings', 'escape'] }
        required
      >
        <Input style={ { width: '100px' } } />
      </Form.Item>
    </>
  )
}
