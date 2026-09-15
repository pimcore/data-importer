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
import { Icon, Tag, Tooltip } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const T = 'data-importer.review'

const STATE_COLOR: Record<string, string> = {
  merged: 'success',
  discarded: 'error',
  refined: 'processing'
}

const STATE_ICON: Record<string, string> = {
  merged: 'check-circle',
  discarded: 'x-circle',
  refined: 'check-circle'
}

interface HistoryHeadProps {
  readonly state: string
  readonly resolvedAt?: number
  readonly styles: Styles
}

/** only a real timestamp is printable: a missing or zero value would read as the epoch */
const formatResolvedAt = (unixMillis: number | undefined): string | null =>
  unixMillis != null && Number.isFinite(unixMillis) && unixMillis > 0 ? new Date(unixMillis).toLocaleString() : null

// the twin of the change-control review header: one quiet line, the long notice in a tooltip
export const HistoryHead: React.FC<HistoryHeadProps> = ({ state, resolvedAt, styles }) => {
  const { t } = useTranslation()
  const when = formatResolvedAt(resolvedAt)

  return (
    <div
      className={ styles.history }
      data-testid="review-history-header"
    >
      <Tag
        color={ STATE_COLOR[state] ?? 'default' }
        iconName={ STATE_ICON[state] }
      >
        { t(`${T}.state.${state}`, state) }
      </Tag>
      { when !== null && (
        <span className={ styles.historyMeta }>
          <Icon value="history" />
          { t(`${T}.resolved-at`, { when, interpolation: { escapeValue: false } }) }
        </span>
      ) }
      <span className={ styles.historyDivider }>|</span>
      <Tooltip
        placement="bottom"
        title={ t(`${T}.history-notice`) }
      >
        <span
          className={ styles.historySnapshot }
          data-testid="review-history-snapshot"
        >
          <Icon value="info" />
          { t(`${T}.snapshot`) }
        </span>
      </Tooltip>
    </div>
  )
}
