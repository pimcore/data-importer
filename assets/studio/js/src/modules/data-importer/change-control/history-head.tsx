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
import { Tag } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const T = 'data-importer.review'

const STATE_COLOR: Record<string, string> = {
  merged: 'success',
  discarded: 'error',
  refined: 'processing'
}

interface HistoryHeadProps {
  readonly state: string
  readonly resolvedAt?: number
  readonly styles: Styles
}

/** only a real timestamp is printable: a missing or zero value would read as the epoch */
const formatResolvedAt = (unixMillis: number | undefined): string | null =>
  unixMillis != null && Number.isFinite(unixMillis) && unixMillis > 0 ? new Date(unixMillis).toLocaleString() : null

/** A resolved change set: what became of it, and that the values below are as they were. */
export const HistoryHead: React.FC<HistoryHeadProps> = ({ state, resolvedAt, styles }) => {
  const { t } = useTranslation()
  const when = formatResolvedAt(resolvedAt)

  return (
    <div className={ styles.history }>
      <div className={ styles.historyState }>
        <Tag color={ STATE_COLOR[state] ?? 'default' }>{ t(`${T}.state.${state}`, state) }</Tag>
        { when !== null && <span>{ t(`${T}.resolved-at`, { when, interpolation: { escapeValue: false } }) }</span> }
      </div>
      <div className={ styles.note }>{ t(`${T}.history-notice`) }</div>
    </div>
  )
}
