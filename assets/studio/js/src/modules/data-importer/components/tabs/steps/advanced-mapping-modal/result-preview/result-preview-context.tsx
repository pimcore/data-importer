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
import { type InterpreterConfig, type LoaderConfig, type MappingConfigItem, type ProcessingConfig, type ResolverConfig } from '../../../../../types'

export interface ResultPreviewContextValue {
  configName: string
  previewRefreshToken: number
  forceRefreshToken: number
  currentMappingItem?: MappingConfigItem
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  calculateTypeError?: string
  isFetchingAttributes: boolean
}

const ResultPreviewContext = createContext<ResultPreviewContextValue | undefined>(undefined)

export function useResultPreviewContext (): ResultPreviewContextValue {
  const ctx = useContext(ResultPreviewContext)
  if (ctx === undefined) {
    throw new Error('useResultPreviewContext must be used within a ResultPreviewProvider')
  }
  return ctx
}

export const ResultPreviewProvider = ({
  children,
  ...value
}: ResultPreviewContextValue & { children: React.ReactNode }): React.JSX.Element => {
  const memoized = useMemo(() => value, [
    value.configName,
    value.previewRefreshToken,
    value.forceRefreshToken,
    value.currentMappingItem,
    value.baseConfig,
    value.calculateTypeError,
    value.isFetchingAttributes
  ])

  return (
    <ResultPreviewContext.Provider value={ memoized }>
      { children }
    </ResultPreviewContext.Provider>
  )
}
