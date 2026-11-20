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
import { act, cleanup, render } from '@testing-library/react'
import { ImportConfigReviewSurface } from './import-config-review-surface'
import type { ReviewPayload } from './review-payload'

const editorProps: Array<Record<string, any>> = []
const listProps: Array<Record<string, any>> = []
const review = { data: undefined as ReviewPayload | undefined, isLoading: false, error: undefined as unknown }
const liveQuery = { data: undefined as unknown, isLoading: false, isError: false }

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useAppDispatch: () => jest.fn(),
  useTranslation: () => ({ t: (key: string) => key })
}))

jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Content: () => <div />,
  Flex: ({ children }: { children?: React.ReactNode }) => <div>{ children }</div>,
  FormAnnotationsProvider: ({ children }: { children?: React.ReactNode }) => <>{ children }</>
}))

jest.mock('@pimcore/change-control-bundle/sdk', () => ({ ReviewOutcomeHeader: () => null }))

jest.mock('../components/data-importer-config-editor', () => ({
  DataImporterConfigEditor: (props: Record<string, unknown>) => {
    editorProps.push(props)
    return null
  }
}))

jest.mock('../components/preview-scope', () => ({
  PreviewScopeProvider: ({ children }: { children?: React.ReactNode }) => <>{ children }</>
}))

jest.mock('../data-importer-api-slice-enhanced', () => ({
  api: { util: { upsertQueryData: jest.fn() } },
  useBundleDataImporterConfigGetQuery: () => liveQuery
}))

jest.mock('./use-change-set-review', () => ({ useChangeSetReview: () => review }))

jest.mock('./change-rail', () => ({
  ChangeList: (props: Record<string, unknown>) => {
    listProps.push(props)
    return null
  }
}))

jest.mock('./config-outline-rail', () => ({ ConfigBriefCard: () => null }))

jest.mock('./import-config-review-surface.styles', () => ({ useStyles: () => ({ styles: {} }) }))

const live = {
  general: { name: 'car-import' },
  loaderConfig: { type: 'sftp', settings: { host: 'old.example.com' } },
  executionConfig: { cronDefinition: '' },
  mappingConfig: [
    { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'vin' } } }
  ]
}

const mapping = {
  current: { mappingConfig: live.mappingConfig },
  proposed: {
    mappingConfig: [
      { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'chassis' } } }
    ]
  }
}

const props = { subjectRef: 'car-import', changeSetId: 'cs-1', contextRef: 'agent-session:7' } as any

const last = (calls: Array<Record<string, any>>): Record<string, any> => calls[calls.length - 1]

beforeEach(() => {
  editorProps.length = 0
  listProps.length = 0
  review.data = undefined
  liveQuery.data = { configuration: live }
  liveQuery.isError = false
})

afterEach(cleanup)

describe('ImportConfigReviewSurface landing', () => {
  it('opens on the mapping step when only mappings changed', () => {
    review.data = { slots: { mapping } } as unknown as ReviewPayload

    render(<ImportConfigReviewSurface { ...props } />)

    expect(last(editorProps)).toMatchObject({ activeTab: 'data-setup', activeStep: 3, changedTabs: ['data-setup'] })
    expect(last(listProps).activeSection).toBe('mapping')
  })

  it('marks every tab that holds a change and keeps a tab the reader picks', () => {
    review.data = {
      slots: {
        execution: { current: { 'executionConfig.cronDefinition': '' }, proposed: { 'executionConfig.cronDefinition': '0 * * * *' } },
        dataSource: { current: { 'loaderConfig.settings.host': 'old.example.com' }, proposed: { 'loaderConfig.settings.host': 'new.example.com' } }
      }
    } as unknown as ReviewPayload

    render(<ImportConfigReviewSurface { ...props } />)
    // the editor's order, not the payload's
    expect(last(editorProps)).toMatchObject({ activeTab: 'data-setup', activeStep: 0, changedTabs: ['data-setup', 'execution'] })

    act(() => { last(editorProps).onTabChange('general') })
    expect(last(editorProps).activeTab).toBe('general')
    expect(last(editorProps).changedTabs).toEqual(['data-setup', 'execution'])
  })

  it('opens a new configuration on General with no tab singled out', () => {
    review.data = { slots: { general: { current: {}, proposed: { 'general.name': 'new-import' } } } } as unknown as ReviewPayload
    liveQuery.data = undefined
    liveQuery.isError = true

    render(<ImportConfigReviewSurface { ...props } />)

    expect(last(editorProps)).toMatchObject({ activeTab: 'general', changedTabs: [] })
  })
})
