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
import { Select, Text, Flex } from '@pimcore/studio-ui-bundle/components'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { useSharedStepStyles } from '../step-shared.styles'
import { useStyles } from './step-source.styles'

export interface StepSourceProps {
  configName: string
  dataSourceIndex: string[]
  columnHeaderOptions: Array<{ value: string, label: string }>
  onDataSourceIndexChange: (v: string[]) => void
  onNext: () => void
}

export const StepSource = ({
  configName,
  dataSourceIndex,
  columnHeaderOptions,
  onDataSourceIndexChange,
  onNext
}: StepSourceProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: shared } = useSharedStepStyles()

  return (
    <>
      {/* Two-column layout */}
      <Flex
        className={ styles.twoColumnLayout }
        gap="extra-small"
      >
        {/* LEFT: Source Attribute(s) */}
        <Flex
          className={ styles.leftColumn }
          gap={ 6 }
          vertical
        >
          <Text
            className={ styles.labelSmall }
            strong
          >
            { t('data-importer.mapping.advanced-modal.step-source.label') }
          </Text>
          <Text
            className={ styles.labelSmall }
            type="secondary"
          >
            { t('data-importer.mapping.advanced-modal.step-source.description') }
          </Text>
          <Select
            className={ styles.selectFull }
            mode="multiple"
            onChange={ onDataSourceIndexChange }
            options={ columnHeaderOptions }
            placeholder={ t('data-importer.mapping.item.source-placeholder') }
            showSearch
            value={ dataSourceIndex }
          />
        </Flex>

        {/* RIGHT: Import Preview — always mounted to preserve fetched data */}
        <Flex
          className={ styles.rightColumn }
          vertical
        >
          <PreviewPanel
            configName={ configName }
            mode="import"
            selectedDataSourceIndex={ dataSourceIndex }
          />
        </Flex>
      </Flex>

      {/* Next step button */}
      <Flex
        className={ styles.footer }
        justify="flex-end"
      >
        <button
          className={ shared.outlineButton }
          onClick={ onNext }
          type="button"
        >
          { t('data-importer.mapping.advanced-modal.next-step') }
        </button>
      </Flex>
    </>
  )
}
