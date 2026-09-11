/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo } from 'react'
import { type DataHubAdapterDetailViewProps, ConfigToolbar, trackConfigError } from '@pimcore/data-hub'
import { useBundleDataImporterConfigGetQuery, useBundleDataImporterConfigSaveMutation } from '../data-importer-api-slice-enhanced'
import { ApiError } from '@pimcore/studio-ui-bundle/modules/app'
import { isNil } from 'lodash'
import { type BackendConfiguration } from '../utils/transformers'
import { DataImporterConfigEditor } from './data-importer-config-editor'

export const DataImporterDetailView = ({ configName, onChange, onDelete }: DataHubAdapterDetailViewProps): React.JSX.Element => {
  // API hooks
  const { data: configData, error: fetchError, isLoading, isFetching, refetch, requestId } = useBundleDataImporterConfigGetQuery(
    { name: configName },
    { refetchOnMountOrArgChange: true }
  )
  const [updateConfig, { isLoading: isSaving }] = useBundleDataImporterConfigSaveMutation()

  // Save errors are surfaced centrally by useDetailView; only the fetch error is reported here.
  useEffect(() => {
    if (!isNil(fetchError)) {
      trackConfigError(fetchError)
    }
  }, [fetchError])

  const loading = isLoading || isFetching

  const backendConfig = useMemo(
    () => (configData?.configuration ?? {}) as BackendConfiguration,
    [configData?.configuration]
  )
  const userPermissions = (configData?.userPermissions ?? {}) as { update?: boolean, delete?: boolean }
  const generalConfig = (backendConfig?.general ?? {}) as { writeable?: boolean }
  const isWriteable = userPermissions.update === true && generalConfig.writeable !== false
  const canDelete = userPermissions.delete === true && generalConfig.writeable !== false
  const saveDisabledTooltipKey = generalConfig.writeable !== false && userPermissions.update !== true ? 'data-hub.config.no-update-permission' : 'config_not_writeable'

  const handleSaveToApi = async (updatedConfig: BackendConfiguration, modificationDate: number): Promise<{ modificationDate?: number }> => {
    const response = await updateConfig({
      name: configName,
      bundleDataImporterConfigurationSaveParameters: {
        configuration: updatedConfig,
        modificationDate
      }
    })

    if ('error' in response) {
      throw new ApiError(response.error ?? {})
    }

    return { modificationDate: response.data?.modificationDate }
  }

  return (
    <DataImporterConfigEditor
      configName={ configName }
      configuration={ backendConfig }
      isLoading={ loading }
      isWriteable={ isWriteable }
      modificationDate={ configData?.modificationDate }
      onChange={ onChange }
      onSave={ handleSaveToApi }
      renderToolbar={ ({ isDirty, onSave }) => (
        <ConfigToolbar
          canDelete={ canDelete }
          configName={ configName }
          isDirty={ isDirty }
          isLoading={ loading }
          isSaving={ isSaving }
          isWriteable={ isWriteable }
          onDelete={ onDelete }
          onRefresh={ refetch }
          onSave={ onSave }
          saveDisabledTooltipKey={ saveDisabledTooltipKey }
        />
      ) }
      requestId={ requestId }
    />
  )
}
