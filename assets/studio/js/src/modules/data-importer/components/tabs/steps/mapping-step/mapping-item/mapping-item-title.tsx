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
import { ChangeTag } from '@pimcore/change-control-bundle/sdk'
import { useStyles } from '../mapping-step.styles'
import { useFormItemAnnotation } from '../../../../../change-control/studio-form-annotations'

export interface MappingItemTitleProps {
  fieldIndex: number
  label: string
}

export const MappingItemTitle = ({ fieldIndex, label }: MappingItemTitleProps): React.JSX.Element => {
  const { styles } = useStyles()
  // a review marks the row as a whole: the row binds no Form.Item of its own
  const annotation = useFormItemAnnotation(['mappingConfig', fieldIndex])

  if (annotation === undefined) {
    return <>{ label }</>
  }

  return (
    <span className={ styles.annotatedTitle }>
      <span className={ annotation.status === 'removed' ? styles.removedTitle : undefined }>{ label }</span>
      <ChangeTag status={ annotation.status } />
    </span>
  )
}
