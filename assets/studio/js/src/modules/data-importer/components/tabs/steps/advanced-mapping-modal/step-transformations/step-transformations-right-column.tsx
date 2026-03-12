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
import { IconButton, Select, Flex } from '@pimcore/studio-ui-bundle/components'
import { type InterpreterConfig, type LoaderConfig, type MappingConfigItem, type ProcessingConfig, type ResolverConfig } from '../../../../../types'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { useSharedStepStyles } from '../step-shared.styles'
import { useStyles } from './step-transformations.styles'

export interface StepTransformationsRightColumnProps {
  configName: string
  dataSourceIndex: string[]
  columnHeaderOptions: Array<{ value: string, label: string }>
  previewRefreshToken: number
  currentMappingItem: MappingConfigItem
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  editingSource: boolean
  onToggleEditing: () => void
  onBlurSource: () => void
  onChangeSource: (v: string[]) => void
  getSourceLabel: (value: string) => string
  onPrev: () => void
  onNext: () => void
}

export const StepTransformationsRightColumn = ({
  configName,
  dataSourceIndex,
  columnHeaderOptions,
  previewRefreshToken,
  currentMappingItem,
  baseConfig,
  editingSource,
  onToggleEditing,
  onBlurSource,
  onChangeSource,
  getSourceLabel,
  onPrev,
  onNext
}: StepTransformationsRightColumnProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: shared } = useSharedStepStyles()

  return (
    <Flex
      className={ styles.rightColumn }
      gap="extra-small"
      justify="space-between"
      vertical
    >
      <Flex
        gap="extra-small"
        vertical
      >
        <Flex
          gap="mini"
          vertical
        >
          <Flex
            align="center"
            className={ styles.sourceSectionHeader }
            gap="mini"
          >
            <span className={ styles.sourceSectionTitle }>
              { t('data-importer.mapping.advanced-modal.step-source.label') }
            </span>
            <IconButton
              icon={ { value: 'edit-pen' } }
              onClick={ onToggleEditing }
              size="small"
              tooltip={ { title: t('data-importer.mapping.advanced-modal.transformer.edit-source') } }
              type="text"
            />
          </Flex>

          { editingSource
            ? (
              <Select
                className={ shared.selectFull }
                mode="multiple"
                onBlur={ onBlurSource }
                onChange={ (v) => { onChangeSource(Array.isArray(v) ? (v as string[]) : []) } }
                options={ columnHeaderOptions }
                showSearch
                value={ dataSourceIndex }
              />
              )
            : (
              <Flex
                className={ styles.sourceValues }
                wrap="wrap"
              >
                { dataSourceIndex.length === 0
                  ? <span className={ styles.emptyState }>{ '—' }</span>
                  : dataSourceIndex.map((v, i) => (
                    <React.Fragment key={ v }>
                      { i > 0 && <span className={ styles.sourceSeparator }>{ ' | ' }</span> }
                      <span>{ getSourceLabel(v) }</span>
                    </React.Fragment>
                  ))
                }
              </Flex>
              ) }
        </Flex>

        <div className={ styles.previewWrapper }>
          <PreviewPanel
            baseConfig={ baseConfig }
            configName={ configName }
            currentMappingItem={ currentMappingItem }
            mode="result"
            refreshToken={ previewRefreshToken }
          />
        </div>
      </Flex>

      <Flex
        gap="extra-small"
        justify="flex-end"
      >
        <button
          className={ shared.outlineButton }
          onClick={ onPrev }
          type="button"
        >
          { t('data-importer.mapping.advanced-modal.previous-step') }
        </button>
        <button
          className={ shared.outlineButton }
          onClick={ onNext }
          type="button"
        >
          { t('data-importer.mapping.advanced-modal.next-step') }
        </button>
      </Flex>
    </Flex>
  )
}
