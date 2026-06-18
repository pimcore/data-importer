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
import { ApiError } from '@pimcore/studio-ui-bundle/modules/app'
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
  const [updateConfig, { isLoading: isSaving }] = useBundleDataImporterConfigSaveMutation()

  // Error tracking. Save errors are surfaced centrally by useDetailView (via .unwrap()); only the
  // fetch error needs to be reported here.
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
  // Editable only with the update permission AND a writeable config; deletable only with the delete
  // permission AND a writeable config (independent of update).
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

  // Shared form state management
  const { form, isDirty, initialValues, handleSave, handleValuesChange } = useDetailView<DataImporterFormValues, BackendConfiguration>({
    configName,
    configData: backendConfig,
    modificationDate: configData?.modificationDate,
    isLoading: loading,
    requestId,
    isWriteable,
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
      canDelete={ canDelete }
      configName={ configName }
      isDirty={ isDirty }
      isLoading={ loading }
      isSaving={ isSaving }
      isWriteable={ isWriteable }
      onDelete={ onDelete }
      onRefresh={ refetch }
      onSave={ handleSave }
      saveDisabledTooltipKey={ saveDisabledTooltipKey }
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
