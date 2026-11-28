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

export interface SparkleIconProps {
  size?: number
  // the notice's icon has a small second sparkle; the chips' does not
  withSecond?: boolean
}

// what was mapped automatically; Studio's icon set has no sparkle
export const SparkleIcon = ({ size = 14, withSecond = false }: SparkleIconProps): React.JSX.Element => (
  <svg
    aria-hidden
    data-icon="sparkle"
    height={ size }
    style={ { flexShrink: 0 } }
    viewBox="0 0 14 14"
    width={ size }
  >
    <path
      d={ withSecond
        ? 'M7 1.5l1.3 3.2 3.2 1.3-3.2 1.3L7 10.5 5.7 7.3 2.5 6l3.2-1.3Z M11.5 9.5l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6Z'
        : 'M7 1.5l1.3 3.2 3.2 1.3-3.2 1.3L7 10.5 5.7 7.3 2.5 6l3.2-1.3Z' }
      fill="currentColor"
    />
  </svg>
)
