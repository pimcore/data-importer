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
import { useStyles } from './section-header.styles'

export interface SectionHeaderProps {
  step: number
  title: string
  expanded: boolean
  onToggle: () => void
  hasBorderBottom: boolean
}

export const SectionHeader = ({ step, title, expanded, onToggle, hasBorderBottom }: SectionHeaderProps): React.JSX.Element => {
  const { token } = theme.useToken()
  const { styles, cx } = useStyles()

  return (
    <div
      className={ cx(styles.row, hasBorderBottom && styles.rowWithBorder) }
      onClick={ onToggle }
    >
      {/* Purple circular badge */}
      <div className={ styles.badge }>
        { step }
      </div>

      <span className={ styles.title }>{ title }</span>

      {/* Chevron — up when expanded, down when collapsed */}
      { expanded
        ? (
          <svg
            fill="none"
            height="16"
            viewBox="0 0 16 16"
            width="16"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M3 10L8 5L13 10"
              stroke={ token.colorTextSecondary }
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="1.5"
            />
          </svg>
          )
        : (
          <svg
            fill="none"
            height="16"
            viewBox="0 0 16 16"
            width="16"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M3 6L8 11L13 6"
              stroke={ token.colorTextSecondary }
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="1.5"
            />
          </svg>
          )
      }
    </div>
  )
}
