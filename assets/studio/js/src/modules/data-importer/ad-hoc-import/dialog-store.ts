/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useSyncExternalStore } from 'react'

export interface AdHocImportTarget {
  folderId: number
  // the folder's path; shown in the title and where new objects go by default
  folderPath: string
  // for the title of the root folder, whose path is empty
  folderLabel: string
}

// A menu item is gone once its menu closes, so the dialog it opens lives in a host of its own
let target: AdHocImportTarget | null = null
const listeners = new Set<() => void>()

const set = (next: AdHocImportTarget | null): void => {
  target = next
  listeners.forEach((listener) => { listener() })
}

export const openAdHocImport = (next: AdHocImportTarget): void => { set(next) }
export const closeAdHocImport = (): void => { set(null) }

const subscribe = (listener: () => void): (() => void) => {
  listeners.add(listener)

  return () => { listeners.delete(listener) }
}

export const useAdHocImportTarget = (): AdHocImportTarget | null => useSyncExternalStore(subscribe, () => target)
