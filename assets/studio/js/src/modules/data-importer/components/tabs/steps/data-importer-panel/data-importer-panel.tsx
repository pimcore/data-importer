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
import { FormKit, Space } from '@pimcore/studio-ui-bundle/components'
import type { PanelProps } from '@pimcore/studio-ui-bundle/components'
import { FieldWidthLimiter } from '../field-width-limiter/field-width-limiter'

export interface DataImporterPanelProps extends PanelProps {
  /** When true, the FieldWidthLimiter (max-width: 300px) is not applied to panel content. */
  noWidthLimit?: boolean
}

export const DataImporterPanel = (props: DataImporterPanelProps): React.JSX.Element => {
  const { children, theme = 'card-with-highlight', contentPadding = 'extra-small', noWidthLimit = false, ...panelProps } = props

  if (theme === 'card-with-highlight') {
    const content = (
      <Space
        className='w-full'
        direction='vertical'
        size='extra-small'
      >
        { children }
      </Space>
    )

    return (
      <FormKit.Panel
        { ...panelProps }
        contentPadding={ contentPadding }
        theme={ theme }
      >
        { noWidthLimit ? content : <FieldWidthLimiter>{ content }</FieldWidthLimiter> }
      </FormKit.Panel>
    )
  }

  const panel = (
    <FormKit.Panel
      { ...panelProps }
      contentPadding={ contentPadding }
      theme={ theme }
    >
      { children }
    </FormKit.Panel>
  )

  return noWidthLimit ? panel : <FieldWidthLimiter>{ panel }</FieldWidthLimiter>
}
