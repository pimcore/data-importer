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
import { renderToStaticMarkup } from 'react-dom/server'
import { PreviewScopeProvider, usePreviewContext, usePreviewScope } from './preview-scope'

const Probe = (): React.JSX.Element => <span>{ `${usePreviewScope() ?? '-'}|${usePreviewContext() ?? '-'}` }</span>

describe('preview scope', () => {
  it('carries the change set and the context it is kept in to the requests below', () => {
    const markup = renderToStaticMarkup(
      <PreviewScopeProvider
        context="agent-session:7"
        scope="cs-1"
      >
        <Probe />
      </PreviewScopeProvider>
    )

    expect(markup).toBe('<span>cs-1|agent-session:7</span>')
  })

  it('is empty outside a review', () => {
    expect(renderToStaticMarkup(<Probe />)).toBe('<span>-|-</span>')
  })
})
