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
import { Icon, Spin } from '@pimcore/studio-ui-bundle/components'
import { useStyles } from './import-step.styles'

export type StepState = 'done' | 'active' | 'pending'

interface StepLineProps {
  label: string
  state: StepState
  detail?: string
}

export const DoneIcon = (): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <span className={ styles.doneIcon }>
      <Icon
        options={ { width: 12, height: 12 } }
        value="check"
      />
    </span>
  )
}

// one step of a running import, with an icon for how far it got
export const StepLine = ({ label, state, detail }: StepLineProps): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <div
      aria-current={ state === 'active' ? 'step' : undefined }
      className={ styles.branchStep }
    >
      { state === 'done' && <DoneIcon /> }
      { state === 'active' && (
        <span className={ styles.branchStepActiveIcon }>
          <Spin type="classic" />
        </span>
      ) }
      { state === 'pending' && <span className={ styles.branchStepPending } /> }
      <span className={ `${styles.branchStepLabel} ${state === 'active' ? styles.branchStepActive : ''}` }>{ label }</span>
      { detail !== undefined && <span className={ styles.stepDetail }>{ detail }</span> }
    </div>
  )
}
