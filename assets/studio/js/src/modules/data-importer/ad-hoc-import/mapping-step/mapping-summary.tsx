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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Flex, Icon, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { type RequiredField, type RowFilter } from './mapping-state'
import { useStyles } from './mapping-step.styles'

const MAPPING = 'data-importer.ad-hoc-import.mapping'
const FILTERS: RowFilter[] = ['all', 'open', 'mapped']

export interface MappingSummaryProps {
  filter: RowFilter
  onFilter: (filter: RowFilter) => void
  counts: Record<RowFilter, number>
  total: number
  skipped: number
  required: RequiredField[]
  // the columns mapped automatically, and those with a suggestion waiting
  auto: number
  pending: number
  onSkipOpen: () => void
  onUseSuggestions: () => void
}

// how far the mapping is: the columns decided, what to show, the required fields and what was mapped for the user
export const MappingSummary = ({ filter, onFilter, counts, total, skipped, required, auto, pending, onSkipOpen, onUseSuggestions }: MappingSummaryProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  let noteRest = t(`${MAPPING}.auto-note.rest-none`)
  if (pending === 1) {
    noteRest = t(`${MAPPING}.auto-note.rest-one`)
  } else if (pending > 1) {
    noteRest = t(`${MAPPING}.auto-note.rest`, { count: pending })
  }

  return (
    <div>
      <div className={ styles.summary }>
        <div className={ styles.summaryCount }>
          <span>{ t(`${MAPPING}.progress`, { mapped: counts.mapped, total }) }</span>
          <div
            aria-hidden
            className={ styles.bar }
          >
            <div
              className={ styles.barMapped }
              style={ { flexGrow: counts.mapped } }
            />
            <div
              className={ styles.barSkipped }
              style={ { flexGrow: skipped } }
            />
            <div style={ { flexGrow: Math.max(total - counts.mapped - skipped, 0) } } />
          </div>
        </div>
        <Flex gap="mini">
          { FILTERS.map((value) => (
            <Button
              key={ value }
              onClick={ () => { onFilter(value) } }
              size="small"
              type={ filter === value ? 'primary' : 'default' }
            >
              { t(`${MAPPING}.filter.${value}`, { count: counts[value] }) }
            </Button>
          )) }
        </Flex>
        { counts.open > 0 && (
          <Button
            onClick={ onSkipOpen }
            size="small"
          >
            { t(`${MAPPING}.skip-open`, { count: counts.open }) }
          </Button>
        ) }
        <span className={ styles.spacer } />
        { required.length > 0 && (
          <Flex
            align="center"
            gap="mini"
            wrap="wrap"
          >
            <Text type="secondary">{ t(`${MAPPING}.required`) }</Text>
            { required.map(({ key, title, covered }) => (
              <Tag
                color={ covered ? 'success' : 'error' }
                key={ key }
              >
                { `${covered ? '✓' : '✗'} ${title}` }
              </Tag>
            )) }
          </Flex>
        ) }
      </div>

      { (auto > 0 || pending > 0) && (
        <div className={ styles.note }>
          <span className={ styles.noteIcon }><Icon value="auto-save" /></span>
          <span>
            <strong>{ auto === 1 ? t(`${MAPPING}.auto-note.lead-one`) : t(`${MAPPING}.auto-note.lead`, { count: auto }) }</strong>
            { ' ' }
            { noteRest }
          </span>
          <span className={ styles.spacer } />
          { pending > 0 && (
            <Button
              onClick={ onUseSuggestions }
              size="small"
              type="primary"
            >
              { t(`${MAPPING}.use-all-suggestions`, { count: pending }) }
            </Button>
          ) }
        </div>
      ) }
    </div>
  )
}
