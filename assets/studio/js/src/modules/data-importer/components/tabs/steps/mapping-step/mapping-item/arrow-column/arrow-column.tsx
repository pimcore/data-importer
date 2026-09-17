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
import cn from 'classnames'
import { theme } from 'antd'
import { Icon } from '@pimcore/studio-ui-bundle/components'
import { useStyles } from '../../mapping-step.styles'
import { MappingArrowIcon } from './mapping-arrow-icon.inline'

export interface ArrowColumnProps {
  isAdvanced: boolean
  isWarningState: boolean
  isInProgressState: boolean
}

export const ArrowColumn = (props: ArrowColumnProps): React.JSX.Element => {
  const { isAdvanced, isWarningState, isInProgressState } = props
  const { styles } = useStyles()
  const { token } = theme.useToken()

  const isComplex = isAdvanced || isWarningState || isInProgressState
  const arrowFill = (isWarningState || isInProgressState) ? token.colorWarning : token.colorIcon

  const arrowSvg = (
    <span className={ styles.arrowSvg }>
      <MappingArrowIcon fill={ arrowFill } />
    </span>
  )

  if (isComplex) {
    return (
      <div className={ cn(styles.arrowCol, styles.arrowColAdvanced) }>
        { isAdvanced && !isWarningState && !isInProgressState && (
          <span className={ styles.arrowAdvancedIcon }>
            <Icon value="transformation" />
          </span>
        ) }
        { (isWarningState || isInProgressState) && (
          <span className={ styles.arrowWarningBadge }>
            <Icon value="warning-circle" />
          </span>
        ) }
        { arrowSvg }
      </div>
    )
  }

  return (
    <div className={ cn(styles.arrowCol, styles.arrowColSimple) }>
      <div className={ styles.arrowLabelSpacer } />
      <div className={ styles.arrowSelectRow }>
        { arrowSvg }
      </div>
    </div>
  )
}
