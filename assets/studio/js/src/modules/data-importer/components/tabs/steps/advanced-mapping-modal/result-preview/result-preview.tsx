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
import { Spin } from '@pimcore/studio-ui-bundle/components'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { ErrorBox } from '../error-box/error-box'
import { useResultPreviewContext } from './result-preview-context'
import { useStyles } from './result-preview.styles'

export const ResultPreview = (): React.JSX.Element => {
  const {
    configName,
    previewRefreshToken,
    forceRefreshToken,
    currentMappingItem,
    baseConfig,
    calculateTypeError,
    isFetchingAttributes
  } = useResultPreviewContext()
  const { styles } = useStyles()

  return (
    <div className={ styles.wrapper }>
      { isFetchingAttributes && <Spin type="classic" /> }
      { !isFetchingAttributes && calculateTypeError !== undefined && (
        <ErrorBox>{ calculateTypeError }</ErrorBox>
      ) }
      { !isFetchingAttributes && calculateTypeError === undefined && (
        <PreviewPanel
          baseConfig={ baseConfig }
          configName={ configName }
          currentMappingItem={ currentMappingItem }
          forceRefreshToken={ forceRefreshToken }
          mode="result"
          refreshToken={ previewRefreshToken }
        />
      ) }
    </div>
  )
}
