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
import { useFieldWidth } from '@pimcore/studio-ui-bundle/modules/element'
import { useStyles } from './field-width-limiter.styles'

export interface FieldWidthLimiterProps {
  children: React.ReactNode
}

export const FieldWidthLimiter = (props: FieldWidthLimiterProps): React.JSX.Element => {
  const { children } = props
  const { medium } = useFieldWidth()
  const { styles } = useStyles(medium)

  return (
    <div className={ styles.container }>
      { children }
    </div>
  )
}
