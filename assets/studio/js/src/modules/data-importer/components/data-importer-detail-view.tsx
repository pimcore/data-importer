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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type DataHubAdapterDetailViewProps, GeneralTab, PermissionsTab, BaseDetailView, type TabItem, ConfigToolbar, useDetailView, trackConfigError } from '@pimcore/data-hub'
import { useBundleDataImporterConfigGetQuery, useBundleDataImporterConfigSaveMutation } from '../data-importer-api-slice-enhanced'
import { ApiError, isBundleActive } from '@pimcore/studio-ui-bundle/modules/app'
import { isNil } from 'lodash'
import { type DataImporterFormValues } from '../types'
import { transformBackendToForm, transformFormToBackend, type BackendConfiguration } from '../utils/transformers'
import { resolveConfigCapabilities, type ConfigGeneralSettings, type ConfigUserPermissions } from '../utils/config-capabilities'
import { ConfigCapabilitiesProvider } from './config-capabilities-context'
import { DataSetupTab } from './tabs/data-setup-tab'
import { ExecutionTab } from './tabs/execution-tab'
import { ImportLogsTab } from './tabs/import-logs-tab'

export const DataImporterDetailView = ({ configName, onChange, onDelete }: DataHubAdapterDetailViewProps): React.JSX.Element => {
  const { t } = useTranslation()

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
      throw new ApiError(response.error ?? {})
    }

    return { modificationDate: response.data?.modificationDate }
  }

  // Shared form state management
  const { form, isDirty, initialValues, handleSave, handleValuesChange } = useDetailView<DataImporterFormValues, BackendConfiguration>({
    configName,
    configData: backendConfig,
    modificationDate: configData?.modificationDate,
    isLoading: loading,
    requestId,
    isWriteable: canSaveConfig,
    transformToForm: transformBackendToForm,
    transformToBackend: transformFormToBackend,
    onSave: handleSaveToApi,
    onChange
  })

  const tabs: TabItem[] = [
    {
      key: 'general',
      label: t('data-importer.tabs.general'),
      children: <GeneralTab adapterTypeLabel={ t('data-importer.adapter.dataImporterDataObject') } />
    },
    {
      key: 'data-setup',
      label: t('data-importer.tabs.data-setup'),
      fullHeight: true,
      children: <DataSetupTab
        configName={ configName }
                />
    },
    {
      key: 'execution',
      label: t('data-importer.tabs.execution'),
      children: <ExecutionTab
        configName={ configName }
        isDirty={ isDirty }
                />
    },
    // Import logs are read through the application logger, so the tab is only
    // available when that bundle is enabled and installed.
    ...(isBundleActive('PimcoreApplicationLoggerBundle')
      ? [{
          key: 'import-logs',
          label: t('data-importer.tabs.import-logs'),
          fullHeight: true,
          children: <ImportLogsTab configName={ configName } />
        }]
      : []),
    {
      key: 'permissions',
      label: t('data-importer.tabs.permissions'),
      children: <PermissionsTab isWriteable={ canSaveConfig } />
    }
  ]

  const toolbar = (
    <ConfigToolbar
      canDelete={ canDeleteConfig }
      configName={ configName }
      isDirty={ isDirty }
      isLoading={ loading }
      isSaving={ isSaving }
      isWriteable={ canSaveConfig }
      onDelete={ onDelete }
      onRefresh={ refetch }
      onSave={ handleSave }
      saveDisabledTooltipKey={ saveDisabledTooltipKey }
    />
  )

  return (
    <ConfigCapabilitiesProvider capabilities={ capabilities }>
      <BaseDetailView
        disabled={ !canSaveConfig }
        form={ form }
        initialValues={ initialValues }
        isLoading={ loading }
        onValuesChange={ handleValuesChange }
        requestId={ requestId ?? '' }
        tabs={ tabs }
        toolbar={ toolbar }
      />
    </ConfigCapabilitiesProvider>
  )
}
