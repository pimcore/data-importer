/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */


import React, { createContext, useContext, useMemo } from 'react'

/**
 * The change set a review is looking at, when it is one. Every preview request the editor's
 * steps make carries it, so a proposal's preview data is kept beside the live
 * configuration's rather than over it — and a configuration that is only proposed so far
 * still has somewhere to keep one. Context, not props: the requests are made five levels
 * below the editor.
 */
interface PreviewScope {
  scope: string | undefined
  // an agent's change set is visible only in the context its author keeps it in
  context: string | undefined
}

const PreviewScopeContext = createContext<PreviewScope>({ scope: undefined, context: undefined })

export const PreviewScopeProvider = ({ scope, context, children }: {
  scope: string | undefined
  context?: string
  children: React.ReactNode
}): React.JSX.Element => {
  const value = useMemo(() => ({ scope, context }), [scope, context])

  return <PreviewScopeContext.Provider value={ value }>{ children }</PreviewScopeContext.Provider>
}

export const usePreviewScope = (): string | undefined => useContext(PreviewScopeContext).scope

export const usePreviewContext = (): string | undefined => useContext(PreviewScopeContext).context
