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
import { Button, Flex } from '@pimcore/studio-ui-bundle/components'
import { type InterpreterConfig, type LoaderConfig, type MappingConfigItem, type ProcessingConfig, type ResolverConfig } from '../../../../../types'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { useStyles } from './step-target.styles'

export interface StepTargetPreviewActionsProps {
  configName: string
  previewRefreshToken: number
  forceRefreshToken: number
  currentMappingItem?: MappingConfigItem
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  onPrev: () => void
  onConfirm: () => void
}

export const StepTargetPreviewActions = ({
  configName,
  previewRefreshToken,
  forceRefreshToken,
  currentMappingItem,
  baseConfig,
  onPrev,
  onConfirm
}: StepTargetPreviewActionsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <Flex
      className={ styles.rightColumn }
      justify="space-between"
      vertical
    >
      <div className={ styles.previewWrapper }>
        <PreviewPanel
          baseConfig={ baseConfig }
          configName={ configName }
          currentMappingItem={ currentMappingItem }
          forceRefreshToken={ forceRefreshToken }
          mode="result"
          refreshToken={ previewRefreshToken }
        />
      </div>

      <Flex
        gap="extra-small"
        justify="flex-end"
      >
        <Button
          onClick={ onPrev }
          type="default"
        >
          { t('data-importer.mapping.advanced-modal.step-target.previous-step') }
        </Button>
        <Button
          onClick={ onConfirm }
          type="primary"
        >
          { t('data-importer.mapping.advanced-modal.step-target.confirm-mapping') }
        </Button>
      </Flex>
    </Flex>
  )
}
