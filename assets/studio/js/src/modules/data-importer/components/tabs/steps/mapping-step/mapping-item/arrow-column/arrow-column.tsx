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
import { theme } from 'antd'
import { Flex, Icon } from '@pimcore/studio-ui-bundle/components'
import { useStyles } from '../../mapping-step.styles'

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
      <svg
        fill="none"
        height="12"
        viewBox="0 0 26 12"
        width="26"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M25.5303 6.05377C25.8232 5.76087 25.8232 5.286 25.5303 4.99311L20.7574 0.220137C20.4645 -0.0727568 19.9896 -0.0727568 19.6967 0.220137C19.4038 0.51303 19.4038 0.987904 19.6967 1.2808L23.9393 5.52344L19.6967 9.76608C19.4038 10.059 19.4038 10.5338 19.6967 10.8267C19.9896 11.1196 20.4645 11.1196 20.7574 10.8267L25.5303 6.05377ZM0 5.52344V6.27344H25V5.52344V4.77344H0V5.52344Z"
          fill={ arrowFill }
          fillOpacity={ 1 }
        />
      </svg>
    </span>
  )

  if (isComplex) {
    return (
      <Flex
        className={ [styles.arrowCol, styles.arrowColAdvanced].join(' ') }
        gap={ 10 }
        justify="center"
        vertical
      >
        { isAdvanced && !isWarningState && !isInProgressState && (
          <span className={ styles.arrowGearIcon }>
            <Icon value="settings" />
          </span>
        ) }
        { (isWarningState || isInProgressState) && (
          <span className={ styles.arrowWarningBadge }>
            <Icon value="warning-circle" />
          </span>
        ) }
        { arrowSvg }
      </Flex>
    )
  }

  return (
    <Flex
      className={ [styles.arrowCol, styles.arrowColSimple].join(' ') }
      justify="flex-start"
      vertical
    >
      {/* Spacer matching label height + gap */}
      <div className={ styles.arrowLabelSpacer } />
      {/* Arrow centred within the select row */}
      <Flex
        align="center"
        className={ styles.arrowSelectRow }
      >
        { arrowSvg }
      </Flex>
    </Flex>
  )
}
