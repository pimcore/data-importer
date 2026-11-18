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
import { SourcesPanel, type SourcesPanelProps } from '../sources-panel/sources-panel'
import { useMappingPreviewMode } from './mapping-preview-mode'

/** The "Sources with preview" panel; with preview mode on it shows the selected record */
export const PreviewSourcesPanel = (props: SourcesPanelProps): React.JSX.Element => {
  const { enabled, record } = useMappingPreviewMode()

  return (
    <SourcesPanel
      { ...props }
      sourceRows={ enabled && record.length > 0 ? record : props.sourceRows }
    />
  )
}
