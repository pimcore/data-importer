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
import { withApiMessage } from '../utils/api-error-message'
import { isNil } from 'lodash'
import { type BackendConfiguration } from '../utils/transformers'
import { resolveConfigCapabilities, type ConfigGeneralSettings, type ConfigUserPermissions } from '../utils/config-capabilities'
import { ConfigCapabilitiesProvider } from './config-capabilities-context'
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
  const capabilities = useMemo(
    () => resolveConfigCapabilities(
      configData?.userPermissions as ConfigUserPermissions | undefined,
      backendConfig?.general as ConfigGeneralSettings | undefined
    ),
    [configData?.userPermissions, backendConfig?.general]
  )
  const { canSaveConfig, canDeleteConfig, saveDisabledTooltipKey } = capabilities

  const handleSaveToApi = async (updatedConfig: BackendConfiguration, modificationDate: number): Promise<{ modificationDate?: number }> => {
    const response = await updateConfig({
      name: configName,
      bundleDataImporterConfigurationSaveParameters: {
        configuration: updatedConfig,
        modificationDate
      }
    })

    if ('error' in response) {
      throw new ApiError(withApiMessage(response.error))
    }

    return { modificationDate: response.data?.modificationDate }
  }

  return (
    <ConfigCapabilitiesProvider capabilities={ capabilities }>
      <DataImporterConfigEditor
        configName={ configName }
        configuration={ backendConfig }
        isLoading={ loading }
        isWriteable={ canSaveConfig }
        modificationDate={ configData?.modificationDate }
        onChange={ onChange }
        onSave={ handleSaveToApi }
        renderToolbar={ ({ isDirty, onSave }) => (
          <ConfigToolbar
            canDelete={ canDeleteConfig }
            configName={ configName }
            isDirty={ isDirty }
            isLoading={ loading }
            isSaving={ isSaving }
            isWriteable={ canSaveConfig }
            onDelete={ onDelete }
            onRefresh={ refetch }
            onSave={ onSave }
            saveDisabledTooltipKey={ saveDisabledTooltipKey }
          />
        ) }
        requestId={ requestId }
      />
    </ConfigCapabilitiesProvider>
  )
}
