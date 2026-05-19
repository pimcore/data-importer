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
import { Flex } from '@pimcore/studio-ui-bundle/components'
import { ResultPreview } from '../result-preview/result-preview'
import { useStyles } from './step-target.styles'

export const StepTargetPreviewActions = (): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <Flex
      className={ styles.rightColumn }
      vertical
    >
      <ResultPreview />
    </Flex>
  )
}
