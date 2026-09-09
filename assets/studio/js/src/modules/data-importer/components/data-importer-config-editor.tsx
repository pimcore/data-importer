/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type DataHubAdapterDetailViewProps, GeneralTab, PermissionsTab, BaseDetailView, type TabItem, useDetailView } from '@pimcore/data-hub'
import { isBundleActive } from '@pimcore/studio-ui-bundle/modules/app'
import { type DataImporterFormValues } from '../types'
import { transformBackendToForm, transformFormToBackend, type BackendConfiguration } from '../utils/transformers'
import { DataSetupTab } from './tabs/data-setup-tab'
import { ExecutionTab } from './tabs/execution-tab'
import { ImportLogsTab } from './tabs/import-logs-tab'

/** a mount that does not report dirty state still has to satisfy useDetailView */
const ignoreChange = (): void => undefined

/** what a toolbar can only know from inside the form */
export interface ConfigEditorToolbarState {
  isDirty: boolean
  onSave: () => void
}

export interface DataImporterConfigEditorProps {
  configName: string
  configuration: BackendConfiguration
  isWriteable: boolean
  onSave: (configuration: BackendConfiguration, modificationDate: number) => Promise<{ modificationDate?: number }>
  isLoading?: boolean
  modificationDate?: number
  onChange?: DataHubAdapterDetailViewProps['onChange']
  requestId?: string
  renderToolbar?: (state: ConfigEditorToolbarState) => React.ReactNode
}

/**
 * The configuration editor, over a configuration it is handed rather than one it fetches.
 * Separating this from {@link DataImporterDetailView} keeps the tab tree renderable against
 * any source of a configuration document - the detail API, a fixture, a story.
 */
export const DataImporterConfigEditor = ({
  configName,
  configuration,
  isWriteable,
  onSave,
  isLoading = false,
  modificationDate,
  onChange = ignoreChange,
  requestId,
  renderToolbar
}: DataImporterConfigEditorProps): React.JSX.Element => {
  const { t } = useTranslation()

  // Shared form state management
  const { form, isDirty, initialValues, handleSave, handleValuesChange } = useDetailView<DataImporterFormValues, BackendConfiguration>({
    configName,
    configData: configuration,
    modificationDate,
    isLoading,
    requestId,
    isWriteable,
    transformToForm: transformBackendToForm,
    transformToBackend: transformFormToBackend,
    onSave,
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
      children: <PermissionsTab isWriteable={ isWriteable } />
    }
  ]

  return (
    <BaseDetailView
      disabled={ !isWriteable }
      form={ form }
      initialValues={ initialValues }
      isLoading={ isLoading }
      onValuesChange={ handleValuesChange }
      requestId={ requestId ?? '' }
      tabs={ tabs }
      toolbar={ renderToolbar?.({ isDirty, onSave: handleSave }) }
    />
  )
}
