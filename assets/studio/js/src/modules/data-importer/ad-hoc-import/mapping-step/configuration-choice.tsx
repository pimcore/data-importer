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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Flex, Form, FormKit, Select, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import { filterByLabel } from '../../utils/select-utils'
import { type InspectedFile } from '../file-step/file-step-state'
import {
  columnLabel,
  findConfiguration,
  missingColumns,
  unusableReason,
  type ImportConfiguration
} from './configuration-state'

const SELECT_WIDTH = 360
const FORM_ITEM_STYLE = { marginBottom: 0 }

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `
}))

export interface ConfigurationChoiceProps {
  inspected?: InspectedFile
  configurations?: ImportConfiguration[]
  isLoading: boolean
  chosen?: string
  onChoose: (name: string | undefined) => void
}

export const ConfigurationChoice = ({
  inspected,
  configurations,
  isLoading,
  chosen,
  onChoose
}: ConfigurationChoiceProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const plain = { interpolation: { escapeValue: false } }

  const reasonOf = (configuration: ImportConfiguration): string | null => {
    const reason = inspected === undefined ? configuration.unusable : unusableReason(configuration, inspected)
    if (reason === null) {
      return null
    }

    return t(`data-importer.ad-hoc-import.configuration.unusable.${reason}`, {
      column: configuration.matchColumn ?? '',
      ...plain
    })
  }

  const options = useMemo(() => (configurations ?? []).map((configuration) => {
    const reason = reasonOf(configuration)

    return {
      value: configuration.name,
      label: reason === null
        ? configuration.name
        : t('data-importer.ad-hoc-import.configuration.unusable-option', { name: configuration.name, reason, ...plain }),
      disabled: reason !== null
    }
  }), [configurations, inspected])

  if (isLoading) {
    return <Skeleton active />
  }

  if (options.length === 0) {
    return <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.configuration.none') }</Text>
  }

  const configuration = findConfiguration(configurations, chosen)
  const usable = configuration !== undefined && reasonOf(configuration) === null
  const missing = usable && inspected !== undefined ? missingColumns(configuration, inspected) : []
  const label = (column: string): string => inspected === undefined ? column : columnLabel(inspected, column)

  const match = (shown: ImportConfiguration): string => {
    const column = label(shown.matchColumn ?? '')
    if (shown.matchBy === 'id') {
      return t('data-importer.ad-hoc-import.configuration.match-object-id', { column, ...plain })
    }

    return shown.matchLanguage === null
      ? t('data-importer.ad-hoc-import.configuration.match-field', { field: shown.matchField, column, ...plain })
      : t('data-importer.ad-hoc-import.configuration.match-field-language', {
          field: shown.matchField,
          language: shown.matchLanguage,
          column,
          ...plain
        })
  }

  return (
    <Flex
      gap="normal"
      vertical
    >
      <Form.Item
        htmlFor="ad-hoc-import-configuration"
        label={ t('data-importer.ad-hoc-import.configuration.label') }
        style={ FORM_ITEM_STYLE }
      >
        <Select
          filterOption={ filterByLabel }
          id="ad-hoc-import-configuration"
          onChange={ (next: string) => { onChoose(next === '' ? undefined : next) } }
          options={ options }
          placeholder={ t('data-importer.ad-hoc-import.configuration.choose') }
          showSearch
          style={ { width: SELECT_WIDTH } }
          value={ usable ? chosen : undefined }
        />
      </Form.Item>

      { usable && (
        <FormKit.Panel title={ configuration.name }>
          <Alert
            message={ t('data-importer.ad-hoc-import.configuration.replaces') }
            showIcon
            type="info"
          />
          <Form.Item
            label={ t('data-importer.ad-hoc-import.objects.class') }
            style={ FORM_ITEM_STYLE }
          >
            <Text>{ configuration.className ?? configuration.classId }</Text>
          </Form.Item>
          <Form.Item
            label={ t('data-importer.ad-hoc-import.objects.match-by') }
            style={ FORM_ITEM_STYLE }
          >
            <Text>{ match(configuration) }</Text>
          </Form.Item>
          <Form.Item
            label={ t('data-importer.ad-hoc-import.objects.new-objects-in') }
            style={ FORM_ITEM_STYLE }
          >
            <Text>{ configuration.newObjectsFolder }</Text>
          </Form.Item>
        </FormKit.Panel>
      ) }

      { missing.length > 0 && (
        <Alert
          message={ t('data-importer.ad-hoc-import.configuration.missing-columns', {
            columns: missing.join(', '),
            ...plain
          }) }
          showIcon
          type="warning"
        />
      ) }
    </Flex>
  )
}
