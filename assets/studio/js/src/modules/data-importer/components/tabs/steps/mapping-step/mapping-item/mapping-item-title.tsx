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
import { Icon } from '@pimcore/studio-ui-bundle/components'
import { useStyles } from '../mapping-step.styles'
import { useFormItemAnnotation } from '../../../../../change-control/studio-form-annotations'

export interface MappingItemTitleProps {
  fieldIndex: number
  label: string
  // the mapping has a source but no destination yet
  warning?: boolean
}

export const MappingItemTitle = ({ fieldIndex, label, warning = false }: MappingItemTitleProps): React.JSX.Element => {
  const { styles } = useStyles()
  // a review marks the row as a whole: the row binds no Form.Item of its own
  const annotation = useFormItemAnnotation(['mappingConfig', fieldIndex])

  const title = warning
    ? (
      <span
        className={ styles.warningTitle }
        data-mapping-warning
      >
        <span className={ styles.warningTitleIcon }>
          <Icon value="warning-circle" />
        </span>
        { label }
      </span>
      )
    : label

  if (annotation === undefined) {
    return <>{ title }</>
  }

  // the change tag stays after the title, the warning icon before it
  return (
    <span className={ styles.annotatedTitle }>
      <span className={ annotation.status === 'removed' ? styles.removedTitle : undefined }>{ title }</span>
      <ChangeTag status={ annotation.status } />
    </span>
  )
}
