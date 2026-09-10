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

interface ConfigEditorMode {
  /** the editor is being read, not edited: no control may offer to change anything */
  readonly readOnly: boolean
}

const ConfigEditorModeContext = createContext<ConfigEditorMode>({ readOnly: false })

/**
 * A disabled form greys out its inputs, but the mapping step's affordances are plain buttons -
 * add a mapping, autofill, delete a row - which stay live and would offer edits a reader cannot
 * make. They ask here instead.
 */
export const ConfigEditorModeProvider = ({ readOnly, children }: {
  readOnly: boolean
  children: React.ReactNode
}): React.JSX.Element => {
  const value = useMemo(() => ({ readOnly }), [readOnly])

  return <ConfigEditorModeContext.Provider value={ value }>{ children }</ConfigEditorModeContext.Provider>
}

export const useConfigEditorReadOnly = (): boolean => useContext(ConfigEditorModeContext).readOnly
