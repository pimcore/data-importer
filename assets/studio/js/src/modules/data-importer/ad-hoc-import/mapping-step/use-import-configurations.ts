/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect } from 'react'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterAdHocImportListConfigurationsQuery } from '../../data-importer-api-slice-enhanced'
import { type ImportConfiguration } from './configuration-state'

export interface ImportConfigurations {
  configurations?: ImportConfiguration[]
  isFetching: boolean
  isError: boolean
}

/**
 * The configurations the import may be mapped with, listed only once they are to be used; a failed listing is reported.
 */
export const useImportConfigurations = (enabled: boolean): ImportConfigurations => {
  const listed = useBundleDataImporterAdHocImportListConfigurationsQuery(undefined, { skip: !enabled })

  useEffect(() => {
    if (listed.isError && listed.error !== undefined) {
      trackError(new ApiError(listed.error))
    }
  }, [listed.isError, listed.error])

  return { configurations: listed.data?.configurations, isFetching: listed.isFetching, isError: listed.isError }
}
