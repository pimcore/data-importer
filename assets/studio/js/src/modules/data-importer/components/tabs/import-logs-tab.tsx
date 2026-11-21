/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { FilterProvider } from '@pimcore/studio-ui-bundle/modules/application-logger'
import React from 'react'
import { ImportLogs } from './import-logs/import-logs'

export interface ImportLogsTabProps {
  configName: string
}

export const ImportLogsTab = (props: ImportLogsTabProps): React.JSX.Element => {
  const { configName } = props

  return (
    <FilterProvider>
      <ImportLogs configName={ configName } />
    </FilterProvider>
  )
}
