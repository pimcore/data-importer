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
import { type DataHubAdapterDetailViewProps, GeneralTab, PermissionsTab, BaseDetailView, type TabItem, ConfigToolbar, useDetailView } from '@pimcore/data-hub'
import { useBundleDataImporterConfigGetQuery, useBundleDataImporterConfigSaveMutation } from '../data-importer-api-slice-enhanced'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { isNil } from 'lodash'
import { type DataImporterFormValues } from '../types'
import { transformBackendToForm, transformFormToBackend, type BackendConfiguration } from '../utils/transformers'
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
  const [updateConfig, { error: updateError, isLoading: isSaving }] = useBundleDataImporterConfigSaveMutation()

  // Error tracking
  useEffect(() => {
    if (!isNil(fetchError)) {
      trackError(new ApiError(fetchError))
    }
  }, [fetchError])

  useEffect(() => {
    if (!isNil(updateError)) {
      trackError(new ApiError(updateError))
    }
  }, [updateError])

  const loading = isLoading || isFetching

  const backendConfig = useMemo(
    () => (configData?.configuration ?? {}) as BackendConfiguration,
    [configData?.configuration]
  )
  const isWriteable = configData?.userPermissions?.update ?? true

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
    {
      key: 'import-logs',
      label: t('data-importer.tabs.import-logs'),
      fullHeight: true,
      children: <ImportLogsTab configName={ configName } />
    },
    {
      key: 'permissions',
      label: t('data-importer.tabs.permissions'),
      children: <PermissionsTab isWriteable={ isWriteable } />
    }
  ]

  const toolbar = (
    <ConfigToolbar
      configName={ configName }
      isDirty={ isDirty }
      isLoading={ loading }
      isSaving={ isSaving }
      isWriteable={ isWriteable }
      onDelete={ onDelete }
      onRefresh={ refetch }
      onSave={ handleSave }
    />
  )

  return (
    <BaseDetailView
      disabled={ !isWriteable }
      form={ form }
      initialValues={ initialValues }
      isLoading={ loading }
      onValuesChange={ handleValuesChange }
      requestId={ requestId ?? '' }
      tabs={ tabs }
      toolbar={ toolbar }
    />
  )
}
