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
import {
  Badge,
  Draggable,
  Flex,
  Form,
  IconButton,
  NoContent,
  Space,
  Tag,
  Text
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from '../mapping-step.styles'

// DnD type constant — must match the value used in mapping-item
export const DND_TYPE = 'data-importer-source-column'

export interface SourceRow {
  dataIndex: string
  label: string
  value: string
}

export interface SourcesPanelProps {
  configName: string
  sourceRows: SourceRow[]
  hasPreviewError: boolean
  activeFilter: string | null
  onSetFilter: (dataIndex: string | null) => void
  onAddMappingFromSource: (dataIndex: string, label: string) => void
}

export const SourcesPanel = ({
  configName,
  sourceRows,
  hasPreviewError,
  activeFilter,
  onSetFilter,
  onAddMappingFromSource
}: SourcesPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  // Watch only dataSourceIndex arrays — label changes do NOT trigger a re-render here.
  const dataSourceIndices = Form.useWatch(
    (values: { mappingConfig?: MappingConfigItem[] }) =>
      (values.mappingConfig ?? []).map((item) => item.dataSourceIndex ?? [])
  )

  // Count how many mapping items reference each dataIndex
  const mappingCountByDataIndex = useMemo<Record<string, number>>(() => {
    const counts: Record<string, number> = {}
    ;(dataSourceIndices ?? []).forEach((indices: string[]) => {
      indices.forEach((idx) => {
        counts[idx] = (counts[idx] ?? 0) + 1
      })
    })
    return counts
  }, [dataSourceIndices])

  const isFilterActive = activeFilter !== null
  const hasPreview = sourceRows.length > 0

  return (
    <Flex
      className={ styles.sourcesPanel }
      vertical
    >
      {/* Header */}
      <Flex
        align="center"
        className={ styles.sourcesHeader }
        justify="space-between"
      >
        <Text className={ styles.sourcesTitle }>
          { t('data-importer.mapping.sources.title') }
        </Text>
        { isFilterActive && (
          <span
            className={ styles.resetViewLink }
            onClick={ () => { onSetFilter(null) } }
          >
            { t('data-importer.mapping.sources.reset-view') }
          </span>
        ) }
      </Flex>

      {/* Source rows or empty state */}
      <Flex
        className={ styles.panelScrollable }
        vertical
      >
        { !hasPreview && hasPreviewError && (
          <Flex
            align="center"
            className={ styles.noContentWrapper }
            justify="center"
          >
            <NoContent text={ t('data-importer.mapping.sources.empty') } />
          </Flex>
        ) }

        { hasPreview && (
          <Space
            className={ styles.sourceRowsSpace }
            direction="vertical"
            size="extra-small"
          >
            { sourceRows.map((row) => {
              const count = mappingCountByDataIndex[row.dataIndex] ?? 0
              const isMapped = count > 0
              const isFaded = isFilterActive && activeFilter !== row.dataIndex

              const handleRowClick = (): void => {
                // Any row click toggles the filter for that source.
                // If this row is already the active filter, clear it.
                onSetFilter(activeFilter === row.dataIndex ? null : row.dataIndex)
              }

              return (
                <div
                  className={ `${styles.sourceRowOuter}${isMapped ? ` ${styles.sourceRowOuterMapped}` : ''}${isFaded ? ` ${styles.sourceRowOuterFaded}` : ''}` }
                  key={ row.dataIndex }
                >
                  <Draggable
                    info={ {
                      type: DND_TYPE,
                      title: row.label,
                      icon: { value: 'workflow' },
                      data: { dataIndex: row.dataIndex, label: row.label }
                    } }
                  >
                    {/* Entire inner row is clickable */}
                    <Flex
                      align="center"
                      className={ `${styles.sourceRowInner}${isMapped ? ` ${styles.sourceRowInnerMapped}` : ` ${styles.sourceRowInnerUnmapped}`}` }
                      onClick={ handleRowClick }
                    >
                      {/* Left: tag + badge (mapped) OR just tag (unmapped) */}
                      <Flex
                        align="center"
                        className={ styles.sourceRowTagArea }
                        gap="extra-small"
                      >
                        <div className={ styles.sourceTagInner }>
                          <Space size="extra-small">
                            <Tag color={ isMapped ? 'purple' : undefined }>
                              { row.label }
                            </Tag>

                            { isMapped && (
                              <Badge
                                className={ styles.badgeMappedCount }
                                count={ count }
                                size="large"
                              />
                            ) }
                          </Space>
                        </div>

                        {/* "+" button — visible on hover for unmapped rows only */}
                        { !isMapped && (
                          <span className={ `source-add-btn ${styles.sourceAddBtn}` }>
                            <IconButton
                              icon={ { value: 'plus-circle' } }
                              onClick={ (e) => {
                                e.stopPropagation()
                                onAddMappingFromSource(row.dataIndex, row.label)
                                onSetFilter(row.dataIndex)
                              } }
                              size="small"
                              tooltip={ { title: t('data-importer.mapping.add') } }
                              type="default"
                            />
                          </span>
                        ) }
                      </Flex>

                      {/* Right: preview value */}
                      <span
                        className={ styles.sourceValue }
                        title={ row.value }
                      >
                        { row.value }
                      </span>
                    </Flex>
                  </Draggable>
                </div>
              )
            }) }
          </Space>
        ) }
      </Flex>
    </Flex>
  )
}
