/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { createContext, useContext } from 'react'
import { type ConfigCapabilities } from '../utils/config-capabilities'

/**
 * Nothing is permitted until the detail view provides the resolved capabilities.
 */
const deniedCapabilities: ConfigCapabilities = {
  canSaveConfig: false,
  canDeleteConfig: false,
  canRunImport: false,
  saveDisabledTooltipKey: 'config_not_writeable'
}

const ConfigCapabilitiesContext = createContext<ConfigCapabilities>(deniedCapabilities)

export interface ConfigCapabilitiesProviderProps {
  capabilities: ConfigCapabilities
  children: React.ReactNode
}

/**
 * Makes the capabilities available to the whole configuration form, including the
 * loader settings that are rendered through the dynamic type registry and can
 * therefore not receive them as a property.
 */
export const ConfigCapabilitiesProvider = ({ capabilities, children }: ConfigCapabilitiesProviderProps): React.JSX.Element => (
  <ConfigCapabilitiesContext.Provider value={ capabilities }>
    { children }
  </ConfigCapabilitiesContext.Provider>
)

export const useConfigCapabilities = (): ConfigCapabilities => useContext(ConfigCapabilitiesContext)
