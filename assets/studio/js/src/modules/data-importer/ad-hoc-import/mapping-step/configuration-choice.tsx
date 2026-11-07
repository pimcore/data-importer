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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Card, Flex, Form, FormKit, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from '../file-step/file-step-state'
import {
  configurationColumnLabel,
  findConfiguration,
  missingColumns,
  unusableReason,
  type ImportConfiguration
} from './configuration-state'

const LIST_WIDTH = 360
const LIST_MAX_HEIGHT = 420
const FORM_ITEM_STYLE = { marginBottom: 0 }

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `,
  list: css`
    flex: 0 0 ${LIST_WIDTH}px;
    max-height: ${LIST_MAX_HEIGHT}px;
    overflow-y: auto;
  `,
  detail: css`
    flex: 1 1 0;
    min-width: 0;
  `,
  card: css`
    cursor: pointer;

    &:focus-visible {
      outline: 2px solid ${token.colorPrimaryBorder};
    }
  `,
  chosen: css`
    &, &.ant-card {
      border: 2px solid ${token.colorPrimary};
    }

    .configuration-name {
      color: ${token.colorPrimary};
    }
  `,
  unusable: css`
    cursor: not-allowed;

    &, &.ant-card {
      background: ${token.colorBgContainerDisabled};
    }

    .configuration-name, .configuration-detail {
      color: ${token.colorTextDisabled};
    }
  `,
  detailLine: css`
    color: ${token.colorTextSecondary};
  `
}))

export interface ConfigurationChoiceProps {
  inspected?: InspectedFile
  configurations?: ImportConfiguration[]
  isLoading: boolean
  // the configurations could not be listed
  isError?: boolean
  chosen?: string
  onChoose: (name: string | undefined) => void
}

export const ConfigurationChoice = ({
  inspected,
  configurations,
  isLoading,
  isError = false,
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

  if (isLoading) {
    return <Skeleton active />
  }

  if (isError) {
    return (
      <Alert
        message={ t('data-importer.ad-hoc-import.configuration.load-failed') }
        showIcon
        type="error"
      />
    )
  }

  if (configurations === undefined || configurations.length === 0) {
    return <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.configuration.none') }</Text>
  }

  const configuration = findConfiguration(configurations, chosen)
  const usable = configuration !== undefined && reasonOf(configuration) === null
  const missing = usable && inspected !== undefined ? missingColumns(configuration, inspected) : []
  const match = (shown: ImportConfiguration): string => {
    const column = configurationColumnLabel(shown, inspected, shown.matchColumn ?? '')
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

  const card = (shown: ImportConfiguration, index: number): React.JSX.Element => {
    const reason = reasonOf(shown)
    const detailId = `ad-hoc-import-configuration-${index}-detail`
    const isChosen = usable && shown.name === chosen
    const className = shown.className ?? shown.classId ?? ''
    const choose = (): void => {
      if (reason === null) {
        onChoose(shown.name)
      }
    }

    return (
      <Card
        aria-checked={ isChosen }
        aria-describedby={ detailId }
        aria-disabled={ reason !== null }
        aria-label={ shown.name }
        className={ [styles.card, isChosen ? styles.chosen : '', reason === null ? '' : styles.unusable].join(' ') }
        key={ shown.name }
        onClick={ choose }
        onKeyDown={ (event: React.KeyboardEvent) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            choose()
          }
        } }
        role="radio"
        tabIndex={ reason === null ? 0 : -1 }
      >
        <Flex vertical>
          <Text
            className="configuration-name"
            strong
          >
            { shown.name }
          </Text>
          <Text
            className={ `configuration-detail ${styles.detailLine}` }
            id={ detailId }
          >
            { reason === null
              ? className
              : t('data-importer.ad-hoc-import.configuration.unusable-detail', { class: className, reason, ...plain }) }
          </Text>
        </Flex>
      </Card>
    )
  }

  return (
    <Flex gap="large">
      <Flex
        className={ styles.list }
        gap="small"
        vertical
      >
        <Text
          id="ad-hoc-import-configurations"
          strong
        >
          { t('data-importer.ad-hoc-import.configuration.list-title') }
        </Text>
        <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.configuration.list-hint') }</Text>
        <Flex
          aria-labelledby="ad-hoc-import-configurations"
          gap="small"
          role="radiogroup"
          vertical
        >
          { configurations.map(card) }
        </Flex>
      </Flex>

      <Flex
        className={ styles.detail }
        gap="normal"
        vertical
      >
        { !usable && <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.configuration.choose') }</Text> }

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
    </Flex>
  )
}
