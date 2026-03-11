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
import { Select, Text } from '@pimcore/studio-ui-bundle/components'
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
      <div className={ styles.twoColumnLayout }>
        {/* LEFT: Source Attribute(s) */}
        <div className={ styles.leftColumn }>
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
        </div>

        {/* RIGHT: Import Preview — always mounted to preserve fetched data */}
        <div className={ styles.rightColumn }>
          <PreviewPanel
            configName={ configName }
            mode="import"
            selectedDataSourceIndex={ dataSourceIndex }
          />
        </div>
      </div>

      {/* Next step button */}
      <div className={ styles.footer }>
        <button
          className={ shared.outlineButton }
          onClick={ onNext }
          type="button"
        >
          { t('data-importer.mapping.advanced-modal.next-step') }
        </button>
      </div>
    </>
  )
}
