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
import { ImportConfigReviewSurface } from './import-config-review-surface'
import type { ReviewPayload } from './review-payload'

// the review renders the real editor, so its tab labels are what a reader sees
const review = { data: undefined as ReviewPayload | undefined, isLoading: false, error: undefined as unknown }
const liveQuery = { data: undefined as unknown, isLoading: false, isError: false }

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useAppDispatch: () => jest.fn(),
  useTranslation: () => ({ t: (key: string) => key })
}))

jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ isBundleActive: () => false }))

jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Content: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  ContentLayout: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  Flex: ({ children }: { children?: React.ReactNode }) => <div>{ children }</div>,
  FormAnnotationsProvider: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  FormKit: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  Tabs: ({ items }: { items: Array<{ key: string, label: React.ReactNode }> }) => (
    <ul>{ items.map((item) => (
      <li
        data-testid={ `tab-${item.key}` }
        key={ item.key }
      >{ item.label }</li>
    )) }</ul>
  )
}))

jest.mock('@pimcore/data-hub', () => ({
  useDetailView: () => ({ form: {}, isDirty: false, initialValues: {}, handleSave: () => {}, handleValuesChange: () => {} }),
  GeneralTab: () => null,
  PermissionsTab: () => null
}))

jest.mock('@pimcore/change-control-bundle/sdk', () => ({ ReviewOutcomeHeader: () => null }))

jest.mock('../components/tabs/data-setup-tab', () => ({ DataSetupTab: () => null }))
jest.mock('../components/tabs/execution-tab', () => ({ ExecutionTab: () => null }))
jest.mock('../components/tabs/import-logs-tab', () => ({ ImportLogsTab: () => null }))
jest.mock('../components/config-editor-shell.styles', () => ({ useEditorShellStyles: () => ({ styles: {} }) }))

jest.mock('../components/preview-scope', () => ({
  PreviewScopeProvider: ({ children }: { children?: React.ReactNode }) => <>{ children }</>
}))

jest.mock('../data-importer-api-slice-enhanced', () => ({
  api: { util: { upsertQueryData: jest.fn() } },
  useBundleDataImporterConfigGetQuery: () => liveQuery
}))

jest.mock('./use-change-set-review', () => ({ useChangeSetReview: () => review }))
jest.mock('./change-rail', () => ({ ChangeList: () => null }))
jest.mock('./config-outline-rail', () => ({ ConfigBriefCard: () => null }))
jest.mock('./import-config-review-surface.styles', () => ({ useStyles: () => ({ styles: {} }) }))

const live = {
  general: { name: 'car-import' },
  loaderConfig: { type: 'sftp', settings: { host: 'old.example.com' } },
  executionConfig: { cronDefinition: '' },
  mappingConfig: []
}

const props = { subjectRef: 'car-import', changeSetId: 'cs-1', contextRef: 'agent-session:7' } as any

beforeEach(() => {
  liveQuery.data = { configuration: live }
  review.data = {
    slots: {
      dataSource: { current: { 'loaderConfig.settings.host': 'old.example.com' }, proposed: { 'loaderConfig.settings.host': 'new.example.com' } },
      execution: { current: { 'executionConfig.cronDefinition': '' }, proposed: { 'executionConfig.cronDefinition': '0 * * * *' } }
    }
  } as unknown as ReviewPayload
})

afterEach(cleanup)

describe('ImportConfigReviewSurface tabs', () => {
  it('shows the tabs holding a change with their plain label, no change dot', () => {
    const { container } = render(<ImportConfigReviewSurface { ...props } />)

    expect(screen.getByTestId('tab-data-setup').textContent).toBe('data-importer.tabs.data-setup')
    expect(screen.getByTestId('tab-execution').textContent).toBe('data-importer.tabs.execution')
    expect(container.querySelector('.ant-badge-dot')).toBeNull()
  })
})
