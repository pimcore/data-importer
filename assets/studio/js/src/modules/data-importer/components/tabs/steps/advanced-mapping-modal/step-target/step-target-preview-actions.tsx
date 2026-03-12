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
import { useSharedStepStyles } from '../step-shared.styles'
import { useStyles } from './step-target.styles'

export interface StepTargetPreviewActionsProps {
  configName: string
  previewRefreshToken: number
  currentMappingItem?: MappingConfigItem
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  onPrev: () => void
  onConfirm: () => void
}

export const StepTargetPreviewActions = ({
  configName,
  previewRefreshToken,
  currentMappingItem,
  baseConfig,
  onPrev,
  onConfirm
}: StepTargetPreviewActionsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: shared } = useSharedStepStyles()

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
          mode="result"
          refreshToken={ previewRefreshToken }
        />
      </div>

      <Flex
        gap="extra-small"
        justify="flex-end"
      >
        <button
          className={ shared.outlineButton }
          onClick={ onPrev }
          type="button"
        >
          { t('data-importer.mapping.advanced-modal.step-target.previous-step') }
        </button>
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
