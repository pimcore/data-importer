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
import { type DataHubAdapterDetailViewProps, GeneralTab, PermissionsTab, useDetailView } from '@pimcore/data-hub'
import { Content, ContentLayout, FormKit, Tabs } from '@pimcore/studio-ui-bundle/components'

import { isBundleActive } from '@pimcore/studio-ui-bundle/modules/app'
import { useEditorShellStyles } from './config-editor-shell.styles'
import { type DataImporterFormValues } from '../types'
import { transformBackendToForm, transformFormToBackend, type BackendConfiguration } from '../utils/transformers'
import { ConfigEditorModeProvider } from './config-editor-mode'
import { DataSetupTab } from './tabs/data-setup-tab'
import { ExecutionTab } from './tabs/execution-tab'
import { ImportLogsTab } from './tabs/import-logs-tab'
/** what BaseDetailView called a TabItem; kept so the tab list reads the same */
interface EditorTab {
  key: string
  label: string
  children: React.ReactNode
  fullHeight?: boolean
}


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
  /**
   * false drops everything that belongs to a configuration as it is RUNNING - the import
   * logs, the run button, the execution status. A configuration that is only proposed has
   * no runtime to show.
   */
  showRuntime?: boolean
  renderToolbar?: (state: ConfigEditorToolbarState) => React.ReactNode
  /** drive the tab from outside; omit to keep the tab strip's own state */
  activeTab?: string
  onTabChange?: (key: string) => void
  /** drive the Data Setup step from outside */
  activeStep?: number
  onStepChange?: (step: number) => void
}

/** the tab keys, so a caller can address one without repeating the strings */
export const CONFIG_TABS = {
  general: 'general',
  dataSetup: 'data-setup',
  execution: 'execution',
  importLogs: 'import-logs',
  permissions: 'permissions'
} as const

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
  renderToolbar,
  showRuntime = true,
  activeTab,
  onTabChange,
  activeStep,
  onStepChange
}: DataImporterConfigEditorProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useEditorShellStyles()

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

  const tabs: EditorTab[] = [
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
        activeStep={ activeStep }
        configName={ configName }
        onStepChange={ onStepChange }
                />
    },
    {
      key: 'execution',
      label: t('data-importer.tabs.execution'),
      children: <ExecutionTab
        configName={ configName }
        isDirty={ isDirty }
        showRuntime={ showRuntime }
                />
    },
    // Import logs are read through the application logger, so the tab is only
    // available when that bundle is enabled and installed.
    ...(showRuntime && isBundleActive('PimcoreApplicationLoggerBundle')
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

  // The same primitives data-hub's BaseDetailView arranges, called directly so the tab
  // strip can be controlled. BaseDetailView passes `defaultActiveKey`, which leaves the
  // active tab as its private state and puts it out of reach of anything that wants to
  // navigate to a field. It holds no other logic, so nothing is lost by composing here.
  const wrappedTabs = tabs.map((tab) => ({
    ...tab,
    children: tab.fullHeight === true ? tab.children : <Content padded>{ tab.children }</Content>
  }))

  return (
    <ConfigEditorModeProvider readOnly={ !isWriteable }>
      <ContentLayout renderToolbar={ renderToolbar?.({ isDirty, onSave: handleSave }) }>
        <Content
          loading={ isLoading }
          overflow={ { x: 'auto', y: 'hidden' } }
        >
          { !isLoading && (
            <div className={ styles.formWrapper }>
              <FormKit
                formProps={ {
                  form,
                  initialValues,
                  layout: 'vertical',
                  onValuesChange: handleValuesChange,
                  disabled: !isWriteable
                } }
                key={ requestId ?? '' }
                wrapInPanel={ false }
              >
                <Tabs
                  activeKey={ activeTab }
                  defaultActiveKey={ CONFIG_TABS.general }
                  fullHeight
                  items={ wrappedTabs }
                  noTabBarMargin
                  onChange={ onTabChange }
                  type="card"
                />
              </FormKit>
            </div>
          ) }
        </Content>
      </ContentLayout>
    </ConfigEditorModeProvider>
  )
}
