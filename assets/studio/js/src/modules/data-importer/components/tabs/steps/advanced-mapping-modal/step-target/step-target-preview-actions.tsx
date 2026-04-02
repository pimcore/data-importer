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
import { ResultPreview } from '../result-preview/result-preview'
import { useStyles } from './step-target.styles'

export interface StepTargetPreviewActionsProps {
  onPrev: () => void
  onConfirm: () => void
}

export const StepTargetPreviewActions = ({
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
      <ResultPreview />

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
