/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createContext, useContext } from 'react'
import { type ClassAttribute } from '../../../../types'

export interface MappingItemContextValue {
  configName: string
  classId: string | undefined
  columnHeaderOptions: Array<{ value: string, label: string }>
  attributesMap: Record<string, ClassAttribute[]>
}

const MappingItemContext = createContext<MappingItemContextValue>({
  configName: '',
  classId: undefined,
  columnHeaderOptions: [],
  attributesMap: {}
})

export const MappingItemContextProvider = MappingItemContext.Provider

export function useMappingItemContext (): MappingItemContextValue {
  return useContext(MappingItemContext)
}
