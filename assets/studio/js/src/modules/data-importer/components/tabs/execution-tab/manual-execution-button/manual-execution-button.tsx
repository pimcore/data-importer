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
import { Button, Form, Tooltip } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export interface ManualExecutionButtonProps {
  isDirty: boolean
  isStarting: boolean
  onStart: () => void
  label: string
}

/** Start Import button that disables itself when loader type is "push". */
export const ManualExecutionButton = (props: ManualExecutionButtonProps): React.JSX.Element => {
  const { isDirty, isStarting, onStart, label } = props
  const { t } = useTranslation()
  const loaderType = Form.useWatch(['loaderConfig', 'type']) as string | undefined
  const push = loaderType === 'push'
  const disabled = push || isDirty || isStarting

  const tooltipTitle = isDirty
    ? t('data-importer.execution.start-import.tooltip-dirty')
    : push
      ? t('data-importer.execution.start-import.tooltip-push')
      : undefined

  return (
    <Tooltip title={ tooltipTitle }>
      { /* span needed so Tooltip works on a disabled button */ }
      <span>
        <Button
          disabled={ disabled }
          loading={ isStarting && !push }
          onClick={ onStart }
          type="primary"
        >
          { label }
        </Button>
      </span>
    </Tooltip>
  )
}
