/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect, useState } from 'react'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterAdHocImportListConfigurationsQuery } from '../../data-importer-api-slice-enhanced'
import { type InspectedFile } from '../file-step/file-step-state'
import { type ConfigurationChoiceProps } from './configuration-choice'
import {
  canContinueWithConfiguration,
  findConfiguration,
  type ImportConfiguration,
  type MapWith
} from './configuration-state'

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

export interface ConfigurationMapping {
  mapWith: MapWith
  setMapWith: (mapWith: MapWith) => void
  choice: ConfigurationChoiceProps
  // the chosen configuration can be used with this file
  canContinue: boolean
}

/**
 * Whether the import is mapped here or with a configuration, and which configuration is chosen.
 */
export const useConfigurationMapping = (inspected: InspectedFile | undefined): ConfigurationMapping => {
  const [mapWith, setMapWith] = useState<MapWith>('here')
  const [chosen, setChosen] = useState<string | undefined>(undefined)
  const { configurations, isFetching, isError } = useImportConfigurations(mapWith === 'configuration')

  return {
    mapWith,
    setMapWith,
    choice: { inspected, configurations, isLoading: isFetching, isError, chosen, onChoose: setChosen },
    canContinue: canContinueWithConfiguration(findConfiguration(configurations, chosen), inspected)
  }
}
