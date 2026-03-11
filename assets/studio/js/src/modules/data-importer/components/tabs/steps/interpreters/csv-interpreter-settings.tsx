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
import { Form, FormKit, Input, Switch } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const CsvInterpreterSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  const singleCharRule = (fieldLabel: string): { validator: (_: unknown, value: string | undefined) => Promise<void> } => ({
    async validator (_: unknown, value: string | undefined): Promise<void> {
      const normalized = value ?? ''
      if (normalized.length <= 1) {
        await Promise.resolve(); return
      }

      await Promise.reject(new Error(t('data-importer.interpreter.csv.single-char-only', { field: fieldLabel })))
    }
  })

  return (
    <FormKit.Panel>
      <Form.Item
        name={ ['interpreterConfig', 'settings', 'skipFirstRow'] }
        valuePropName="checked"
      >
        <Switch labelRight={ t('data-importer.interpreter.csv.skip-first-row') } />
      </Form.Item>

      <Form.Item
        name={ ['interpreterConfig', 'settings', 'saveHeaderName'] }
        valuePropName="checked"
      >
        <Switch labelRight={ t('data-importer.interpreter.csv.save-header-name') } />
      </Form.Item>

      <Form.Item
        initialValue=","
        label={ t('data-importer.interpreter.csv.delimiter') }
        name={ ['interpreterConfig', 'settings', 'delimiter'] }
        required
        rules={ [
          { required: true, message: t('data-importer.interpreter.csv.required', { field: t('data-importer.interpreter.csv.delimiter') }) },
          singleCharRule(t('data-importer.interpreter.csv.delimiter'))
        ] }
      >
        <Input />
      </Form.Item>

      <Form.Item
        initialValue='"'
        label={ t('data-importer.interpreter.csv.enclosure') }
        name={ ['interpreterConfig', 'settings', 'enclosure'] }
        required
        rules={ [
          { required: true, message: t('data-importer.interpreter.csv.required', { field: t('data-importer.interpreter.csv.enclosure') }) },
          singleCharRule(t('data-importer.interpreter.csv.enclosure'))
        ] }
      >
        <Input />
      </Form.Item>

      <Form.Item
        initialValue="\"
        label={ t('data-importer.interpreter.csv.escape') }
        name={ ['interpreterConfig', 'settings', 'escape'] }
        required
        rules={ [
          singleCharRule(t('data-importer.interpreter.csv.escape'))
        ] }
      >
        <Input />
      </Form.Item>
    </FormKit.Panel>
  )
}
