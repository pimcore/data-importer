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
import { Checkbox, Flex, IconButton, NoContent, Tag } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useTheme } from 'antd-style'
import { type MappingSuggestion } from '../utils/compute-autofill-suggestions'
import { createMappingItem } from '../utils/mapping-factory'
import { type MappingConfigItem } from '../../../../../types'
import { MappingArrowIcon } from '../mapping-item/arrow-column/mapping-arrow-icon.inline'
import { useStyles } from './autofill-suggestions-panel.styles'

export interface AutofillSuggestionsPanelProps {
  suggestions: MappingSuggestion[]
  selectedIds: Set<string>
  onToggle: (id: string) => void
  previewRow: Record<string, string | null>
  previewRowIndex: number
  hasPrevRow: boolean
  isLoadingPreviewRow: boolean
  onPrevRow: () => void
  onNextRow: () => void
}

function scoreDotClass (score: number, styles: Record<string, string>): string {
  if (score >= 90) return styles.dotGreen
  if (score >= 70) return styles.dotYellow
  return styles.dotOrange
}

export const AutofillSuggestionsPanel = ({
  suggestions,
  selectedIds,
  onToggle,
  previewRow,
  previewRowIndex,
  hasPrevRow,
  isLoadingPreviewRow,
  onPrevRow,
  onNextRow
}: AutofillSuggestionsPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const theme = useTheme()

  if (suggestions.length === 0) {
    return (
      <div className={ styles.emptyState }>
        <NoContent text={ t('data-importer.mapping.autofill-suggestions.empty') } />
      </div>
    )
  }

  return (
    <Flex vertical>
      <Flex
        align="center"
        className={ styles.tableHeaderRow }
        gap="small"
      >
        <div className={ styles.checkboxCell } />
        <div className={ styles.scoreCell }>
          <span className={ styles.tableHeaderCell }>
            { t('data-importer.mapping.autofill-suggestions.score') }
          </span>
        </div>
        <div className={ styles.sourceCell }>
          <span className={ styles.tableHeaderCell }>
            { t('data-importer.mapping.autofill-suggestions.source') }
          </span>
        </div>
        <div className={ styles.arrowCell } />
        <div className={ styles.destinationCell }>
          <span className={ styles.tableHeaderCell }>
            { t('data-importer.mapping.autofill-suggestions.destination') }
          </span>
        </div>
        <div className={ styles.resultCell }>
          <Flex
            align="center"
            justify="space-between"
          >
            <span className={ styles.tableHeaderCell }>
              { t('data-importer.mapping.autofill-suggestions.result') }
            </span>
            <Flex
              align="center"
              gap={ 4 }
            >
              <IconButton
                disabled={ !hasPrevRow || isLoadingPreviewRow }
                icon={ { value: 'chevron-left' } }
                onClick={ onPrevRow }
                size="small"
                type="text"
              />
              <IconButton
                disabled={ isLoadingPreviewRow }
                icon={ { value: 'chevron-right' } }
                onClick={ onNextRow }
                size="small"
                type="text"
              />
            </Flex>
          </Flex>
        </div>
      </Flex>

      { suggestions.map((suggestion) => (
            <Flex
              align="center"
              className={ styles.tableRow }
              gap="small"
              key={ suggestion.id }
              onClick={ () => { onToggle(suggestion.id) } }
            >
              <div className={ styles.checkboxCell }>
                <Checkbox
                  checked={ selectedIds.has(suggestion.id) }
                  onChange={ () => { onToggle(suggestion.id) } }
                  onClick={ (e) => { e.stopPropagation() } }
                />
              </div>
              <div className={ styles.scoreCell }>
                <Flex
                  align="center"
                  gap={ 6 }
                >
                  <span className={ styles.scoreText }>{ `${suggestion.score}%` }</span>
                  <span className={ scoreDotClass(suggestion.score, styles) } />
                </Flex>
              </div>
              <div className={ styles.sourceCell }>
                <span className={ styles.cellText }>{ `${suggestion.sourceLabel} [${suggestion.sourceIndex}]` }</span>
              </div>
              <div className={ styles.arrowCell }>
                <MappingArrowIcon fill={ theme.colorTextTertiary } />
              </div>
              <div className={ styles.destinationCell }>
                <span className={ styles.cellText }>
                  { `Direct, ${suggestion.targetFieldLabel}` }
                </span>
                { suggestion.language !== null && (
                  <Tag className={ styles.localeTag }>
                    { suggestion.language }
                  </Tag>
                ) }
              </div>
              <div className={ styles.resultCell }>
                { previewRow[suggestion.sourceIndex] ?? '' }
              </div>
            </Flex>
          )) }
    </Flex>
  )
}

export function applySelectedSuggestions (
  suggestions: MappingSuggestion[],
  selectedIds: Set<string>
): MappingConfigItem[] {
  return suggestions
    .filter((s) => selectedIds.has(s.id))
    .map((s) => createMappingItem(
      s.sourceIndex,
      s.sourceLabel,
      'autofill',
      s.targetFieldName,
      s.language ?? undefined
    ))
}
