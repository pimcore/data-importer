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
import { cleanup, render, screen } from '@testing-library/react'
import { DataImporterConfigEditor } from './data-importer-config-editor'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({ t: (key: string) => key })
}))

jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  isBundleActive: () => false
}))

const mockFormProps: Array<Record<string, unknown>> = []

jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Content: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  ContentLayout: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  FormKit: ({ children, formProps }: { children?: React.ReactNode, formProps: Record<string, unknown> }) => {
    mockFormProps.push(formProps)
    return <>{ children }</>
  },
  Tabs: ({ items }: { items: Array<{ key: string, label: React.ReactNode, children?: React.ReactNode }> }) => (
    <ul>{ items.map((item) => <li key={ item.key }>{ item.label }{ item.children }</li>) }</ul>
  )
}))

jest.mock('@pimcore/data-hub', () => ({
  useDetailView: () => ({ form: {}, isDirty: false, initialValues: {}, handleSave: () => {}, handleValuesChange: () => {} }),
  GeneralTab: () => null,
  PermissionsTab: () => null
}))

// the mapping step's buttons read the editor's mode; the stand-in shows what they would read
jest.mock('./tabs/data-setup-tab', () => {
  const { useConfigEditorReadOnly } = jest.requireActual('./config-editor-mode')
  return { DataSetupTab: () => <span data-testid="data-setup-read-only">{ String(useConfigEditorReadOnly()) }</span> }
})
jest.mock('./tabs/execution-tab', () => ({ ExecutionTab: () => null }))
jest.mock('./tabs/import-logs-tab', () => ({ ImportLogsTab: () => null }))
jest.mock('./config-editor-shell.styles', () => ({ useEditorShellStyles: () => ({ styles: {} }) }))

const editor = (changedTabs?: string[], isWriteable = false): React.JSX.Element => (
  <DataImporterConfigEditor
    changedTabs={ changedTabs }
    configName="car-import"
    configuration={ {} as any }
    isWriteable={ isWriteable }
    onSave={ async () => await Promise.resolve({}) }
  />
)

afterEach(() => {
  cleanup()
  mockFormProps.length = 0
})

describe('DataImporterConfigEditor', () => {
  it('puts a change dot on each tab that holds a change', () => {
    render(editor(['data-setup', 'permissions']))

    expect(screen.getByTestId('tab-changed-data-setup').textContent).toBe('data-importer.tabs.data-setup')
    expect(screen.getByTestId('tab-changed-permissions')).toBeTruthy()
    expect(screen.queryByTestId('tab-changed-general')).toBeNull()
    expect(screen.getByTestId('tab-changed-data-setup').querySelector('.ant-badge-dot')).not.toBeNull()
  })

  it('marks no tab without changes', () => {
    render(editor())

    expect(screen.queryAllByTestId(/^tab-changed-/)).toHaveLength(0)
    expect(screen.getByText('data-importer.tabs.general')).toBeTruthy()
  })

  it('disables the form and tells the steps they are read, not edited, when not writeable', () => {
    render(editor())

    expect(mockFormProps[mockFormProps.length - 1].disabled).toBe(true)
    expect(screen.getByTestId('data-setup-read-only').textContent).toBe('true')
  })

  it('leaves the form editable when writeable', () => {
    render(editor(undefined, true))

    expect(mockFormProps[mockFormProps.length - 1].disabled).toBe(false)
    expect(screen.getByTestId('data-setup-read-only').textContent).toBe('false')
  })
})
