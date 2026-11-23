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
import { Alert, Card, Flex, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from '../file-step/file-step-state'
import {
  configurationColumnLabel,
  findConfiguration,
  missingColumns,
  unusableReason,
  type ImportConfiguration
} from './configuration-state'
import { ConfigurationDetail } from './configuration-detail'

const LIST_WIDTH = 360
const LIST_MAX_HEIGHT = 420
const CONFIGURATION = 'data-importer.ad-hoc-import.configuration'

const NEXT_KEYS = ['ArrowDown', 'ArrowRight']
const PREVIOUS_KEYS = ['ArrowUp', 'ArrowLeft']

/**
 * The radio pattern's arrow keys: focus and choose the next or previous usable card, wrapping around.
 */
const moveFocus = (from: HTMLElement, step: number): HTMLElement | undefined => {
  const group = from.closest('[role="radiogroup"]')
  const usable = Array.from(group?.querySelectorAll<HTMLElement>('[role="radio"]:not([aria-disabled="true"])') ?? [])
  if (usable.length === 0) {
    return undefined
  }

  const index = usable.indexOf(from)
  const target = usable[(index + step + usable.length) % usable.length]
  target.focus()

  return target
}

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `,
  list: css`
    flex: 0 0 ${LIST_WIDTH}px;
    max-height: ${LIST_MAX_HEIGHT}px;
    overflow-y: auto;
    && [role='radiogroup'] { gap: ${token.marginXS}px; } /* compact cards, close together, as the design's list */
    && .ant-card-body { padding: ${token.paddingXS}px ${token.paddingSM}px; }
  `,
  detail: css`
    flex: 1 1 0;
    min-width: 0;
    && .ant-alert-warning :is(.ant-alert-message, .ant-alert-title) { color: ${token.Colors.Brand.Warning.colorWarningText}; }
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
  detailLine: css`color: ${token.colorTextSecondary};`
}))

// what a card says of a usable configuration: what it reads, how many fields it maps and when it runs
export const summaryOf = (t: ReturnType<typeof useTranslation>['t'], configuration: ImportConfiguration): string => [
  configuration.format?.toUpperCase() ?? '',
  t(`${CONFIGURATION}.${configuration.fieldCount === 1 ? 'field-count-one' : 'field-count'}`, { count: configuration.fieldCount }),
  configuration.schedule === null
    ? t(`${CONFIGURATION}.schedule.manual`)
    : t(`${CONFIGURATION}.schedule.cron`, { cron: configuration.schedule, interpolation: { escapeValue: false } })
].filter((part) => part !== '').join(' · ')

// the usable configurations first, each part in the server's order
export const usableFirst = <T, >(configurations: T[], isUsable: (configuration: T) => boolean): T[] => [
  ...configurations.filter(isUsable),
  ...configurations.filter((configuration) => !isUsable(configuration))
]

export interface ConfigurationChoiceProps {
  inspected?: InspectedFile
  configurations?: ImportConfiguration[]
  isLoading: boolean
  // the configurations could not be listed
  isError?: boolean
  chosen?: string
  onChoose: (name: string | undefined) => void
  // the uploaded file, which the configuration reads instead of its own source
  fileName?: string
}

export const ConfigurationChoice = ({
  inspected,
  configurations,
  isLoading,
  isError = false,
  chosen,
  onChoose,
  fileName
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

  // one tab stop in the group: the chosen card, else the first usable one
  const tabStop = usable ? configuration.name : configurations.find((shown) => reasonOf(shown) === null)?.name

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
        contentPadding="none"
        key={ shown.name }
        onClick={ choose }
        onKeyDown={ (event: React.KeyboardEvent<HTMLElement>) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            choose()

            return
          }

          const step = NEXT_KEYS.includes(event.key) ? 1 : PREVIOUS_KEYS.includes(event.key) ? -1 : 0
          if (step !== 0) {
            event.preventDefault()
            const target = moveFocus(event.currentTarget, step)
            const name = target?.getAttribute('aria-label')
            if (name !== null && name !== undefined) {
              onChoose(name)
            }
          }
        } }
        role="radio"
        tabIndex={ shown.name === tabStop ? 0 : -1 }
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
              ? summaryOf(t, shown)
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
          role="radiogroup"
          vertical
        >
          { usableFirst(configurations, (shown) => reasonOf(shown) === null).map((shown, index) => card(shown, index)) }
        </Flex>
      </Flex>

      <Flex
        className={ styles.detail }
        gap="normal"
        vertical
      >
        { !usable && <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.configuration.choose') }</Text> }

        { usable && (
          <ConfigurationDetail
            configuration={ configuration }
            fileName={ fileName }
            inspected={ inspected }
            match={ configuration.matchBy === 'none' ? t(`${CONFIGURATION}.match-none`) : match(configuration) }
          />
        ) }

        { missing.length > 0 && (
          <Alert
            message={ t(`${CONFIGURATION}.${missing.length === 1 ? 'missing-columns-one' : 'missing-columns'}`, {
              count: missing.length,
              columns: missing.map((column) => `“${column}”`).join(', '),
              ...plain
            }) }
            type="warning"
          />
        ) }
      </Flex>
    </Flex>
  )
}
