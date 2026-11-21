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
import { act, cleanup, render, screen } from '@testing-library/react'
import { ImportConfigReviewSurface } from './import-config-review-surface'
import type { ConfigChange } from './config-review-model'
import type { ReviewPayload } from './review-payload'

const editorProps: Array<Record<string, any>> = []
const listProps: Array<Record<string, any>> = []
const briefProps: Array<Record<string, any>> = []
const annotationProps: Array<Record<string, any>> = []
const outcomeProps: Array<Record<string, any>> = []
const dispatch = jest.fn()
const upsertQueryData = jest.fn((...args: unknown[]) => ({ type: 'upsert', args }))
const review = { data: undefined as ReviewPayload | undefined, isLoading: false, error: undefined as unknown }
const liveQuery = { data: undefined as unknown, isLoading: false, isError: false }

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useAppDispatch: () => dispatch,
  useTranslation: () => ({ t: (key: string) => key })
}))

jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Content: ({ loading }: { loading?: boolean }) => <div data-testid={ loading === true ? 'loading' : 'content' } />,
  Flex: ({ children }: { children?: React.ReactNode }) => <div>{ children }</div>,
  FormAnnotationsProvider: ({ annotations, children }: { annotations: unknown, children?: React.ReactNode }) => {
    annotationProps.push({ annotations })
    return <>{ children }</>
  }
}))

jest.mock('@pimcore/change-control-bundle/sdk', () => ({
  ReviewOutcomeHeader: (props: Record<string, unknown>) => {
    outcomeProps.push(props)
    return <div data-testid="outcome" />
  }
}))

jest.mock('../components/data-importer-config-editor', () => ({
  // antd keeps every pane mounted and flags the shown one; the mark sits in the shown pane
  DataImporterConfigEditor: (props: Record<string, unknown>) => {
    editorProps.push(props)
    return (
      <div>
        <div className="ant-tabs-tabpane"><div data-review-mark="hidden" /></div>
        <div className="ant-tabs-tabpane ant-tabs-tabpane-active"><div data-review-mark="shown" /></div>
      </div>
    )
  }
}))

jest.mock('../components/preview-scope', () => ({
  PreviewScopeProvider: ({ children }: { children?: React.ReactNode }) => <>{ children }</>
}))

jest.mock('../data-importer-api-slice-enhanced', () => ({
  api: { util: { upsertQueryData: (...args: unknown[]) => upsertQueryData(...args) } },
  useBundleDataImporterConfigGetQuery: () => liveQuery
}))

jest.mock('./use-change-set-review', () => ({
  useChangeSetReview: () => review
}))

jest.mock('./change-rail', () => ({
  ChangeList: (props: Record<string, unknown>) => {
    listProps.push(props)
    return <div data-testid="change-list" />
  }
}))

jest.mock('./config-outline-rail', () => ({
  ConfigBriefCard: (props: Record<string, unknown>) => {
    briefProps.push(props)
    return <div data-testid="brief" />
  }
}))

jest.mock('./import-config-review-surface.styles', () => ({
  useStyles: () => ({ styles: {} })
}))

const live = {
  general: { name: 'car-import', active: true },
  loaderConfig: { type: 'sftp', settings: { host: 'old.example.com' } },
  resolverConfig: { loadingStrategy: { type: 'notLoad' } },
  mappingConfig: [
    { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'vin' } } }
  ]
}

const change = {
  slots: {
    dataSource: { current: { 'loaderConfig.settings.host': 'old.example.com' }, proposed: { 'loaderConfig.settings.host': 'new.example.com' } },
    resolver: { current: { 'resolverConfig.loadingStrategy.type': 'notLoad' }, proposed: { 'resolverConfig.loadingStrategy.type': 'path' } },
    mapping: {
      current: { mappingConfig: live.mappingConfig },
      proposed: {
        mappingConfig: [
          { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'chassis' } } }
        ]
      }
    }
  }
} as unknown as ReviewPayload

const creation = {
  slots: { general: { current: {}, proposed: { 'general.name': 'new-import', 'general.active': false } } }
} as unknown as ReviewPayload

const props = { subjectRef: 'car-import', changeSetId: 'cs-1', contextRef: 'agent-session:7' } as any

const last = (calls: Array<Record<string, any>>): Record<string, any> => calls[calls.length - 1]

// jsdom lays nothing out: the editor is 100px high, a mark sits wherever the test puts it
const layOut = (markTop: number): jest.Mock => {
  const scrollIntoView = jest.fn()
  jest.spyOn(Element.prototype, 'getClientRects').mockImplementation(function (this: Element) {
    return (this.hasAttribute('data-review-mark') ? [{}] : []) as unknown as DOMRectList
  })
  jest.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
    const top = this.hasAttribute('data-review-mark') ? markTop : 0
    const rect: Pick<DOMRect, 'top' | 'bottom'> = { top, bottom: top + (this.hasAttribute('data-review-mark') ? 20 : 100) }
    return rect as DOMRect
  })
  Element.prototype.scrollIntoView = scrollIntoView
  return scrollIntoView
}

beforeEach(() => {
  jest.useFakeTimers()
  for (const calls of [editorProps, listProps, briefProps, annotationProps, outcomeProps]) calls.length = 0
  dispatch.mockReset()
  upsertQueryData.mockClear()
  review.data = undefined
  review.isLoading = false
  review.error = undefined
  liveQuery.data = { configuration: live }
  liveQuery.isLoading = false
  liveQuery.isError = false
})

afterEach(() => {
  cleanup()
  jest.restoreAllMocks()
  jest.useRealTimers()
})

describe('ImportConfigReviewSurface', () => {
  it('says the review could not be loaded', () => {
    review.error = new Error('500')

    render(<ImportConfigReviewSurface { ...props } />)

    expect(screen.getByText('data-importer.review.load-failed')).toBeTruthy()
  })

  it('shows a spinner and counts nothing until the change set is read', () => {
    const onStatsChange = jest.fn()

    render(<ImportConfigReviewSurface { ...{ ...props, onStatsChange } } />)

    expect(screen.getByTestId('loading')).toBeTruthy()
    expect(onStatsChange).not.toHaveBeenCalled()
  })

  it('mounts the proposed configuration read-only, marks what changed and counts it', async () => {
    review.data = change
    const onStatsChange = jest.fn()

    render(<ImportConfigReviewSurface { ...{ ...props, onStatsChange } } />)

    const editor = last(editorProps)
    expect(editor).toMatchObject({ configName: 'car-import', isWriteable: false, showRuntime: false, activeTab: 'general' })
    expect(editor.configuration.loaderConfig.settings.host).toBe('new.example.com')
    expect(editor.configuration.mappingConfig[0].dataTarget.settings.fieldName).toBe('chassis')
    await expect(editor.onSave()).resolves.toEqual({})

    // two leaves and one mapping row
    expect(onStatsChange).toHaveBeenCalledWith(3)
    const list = last(listProps)
    expect(list.groups.map((group: { section: string }) => group.section)).toEqual(['dataSource', 'resolver'])
    expect(list.mappings).toHaveLength(1)
    expect(list.activeSection).toBe('general')
    const labelFor = list.labelFor as (c: ConfigChange) => string
    const first: ConfigChange = list.groups[0].changes[0]
    expect(typeof labelFor(first)).toBe('string')
    const annotations: Record<string, unknown> = last(annotationProps).annotations
    expect(Object.keys(annotations)).toEqual([
      'loaderConfig.settings.host',
      'resolverConfig.loadingStrategy.type',
      'mappingConfig.0'
    ])
    expect(screen.queryByTestId('brief')).toBeNull()
    expect(screen.queryByTestId('outcome')).toBeNull()
    expect(dispatch).not.toHaveBeenCalled()
  })

  it('tells a new configuration in one brief, marks no field and stands it in for the missing live one', () => {
    review.data = creation
    liveQuery.data = undefined
    liveQuery.isError = true

    render(<ImportConfigReviewSurface { ...props } />)

    expect(screen.getByTestId('brief')).toBeTruthy()
    expect(screen.queryByTestId('change-list')).toBeNull()
    expect(last(annotationProps).annotations).toEqual({})
    expect(upsertQueryData).toHaveBeenCalledWith('bundleDataImporterConfigGet', { name: 'car-import' }, {
      name: 'car-import',
      configuration: expect.objectContaining({ general: { name: 'new-import', active: false } }),
      userPermissions: { update: false, delete: false },
      modificationDate: 0
    })
    expect(dispatch).toHaveBeenCalledWith(expect.objectContaining({ type: 'upsert' }))
  })

  it('heads a resolved change set with its outcome', () => {
    review.data = change

    render(<ImportConfigReviewSurface { ...{ ...props, compareAgainst: 'base', resolvedAt: 1700000000, state: 'merged' } } />)

    expect(last(outcomeProps)).toEqual({ resolvedAt: 1700000000, state: 'merged' })
  })

  it('carries the reader to the first shown mark of the section a rail row names', () => {
    review.data = change
    const scrollIntoView = layOut(500)

    render(<ImportConfigReviewSurface { ...props } />)
    act(() => { last(listProps).onJump('resolver') })

    expect(last(editorProps)).toMatchObject({ activeTab: 'data-setup', activeStep: 2 })
    expect(last(listProps).activeSection).toBe('resolver')
    expect(scrollIntoView).not.toHaveBeenCalled()

    act(() => { jest.advanceTimersByTime(250) })
    expect(scrollIntoView).toHaveBeenCalledTimes(1)
    expect(scrollIntoView.mock.contexts[0]).toBe(document.querySelector('[data-review-mark="shown"]'))
    expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' })

    // the section already shown scrolls at once, without switching anything
    act(() => { last(listProps).onJump('resolver') })
    expect(scrollIntoView).toHaveBeenCalledTimes(2)

    // a section the editor has no place for goes nowhere
    act(() => { last(listProps).onJump('nowhere') })
    expect(last(editorProps)).toMatchObject({ activeTab: 'data-setup', activeStep: 2 })
  })

  it('leaves a mark in view where it is, and stops looking once it has tried long enough', () => {
    review.data = change
    const scrollIntoView = layOut(10)

    render(<ImportConfigReviewSurface { ...props } />)
    act(() => { last(listProps).onJump('dataSource') })
    act(() => { jest.advanceTimersByTime(250) })
    expect(scrollIntoView).not.toHaveBeenCalled()
    expect(jest.getTimerCount()).toBe(0)

    // no laid-out mark: the look is retried a while, then given up
    jest.spyOn(Element.prototype, 'getClientRects').mockReturnValue([] as unknown as DOMRectList)
    act(() => { last(listProps).onJump('execution') })
    act(() => { jest.advanceTimersByTime(250 * 30) })
    expect(scrollIntoView).not.toHaveBeenCalled()
    expect(jest.getTimerCount()).toBe(0)
  })

  it('lets a new jump stop the look of the previous one', () => {
    review.data = change
    const scrollIntoView = layOut(500)
    jest.spyOn(Element.prototype, 'getClientRects').mockReturnValue([] as unknown as DOMRectList)

    render(<ImportConfigReviewSurface { ...props } />)
    const looks = jest.spyOn(Element.prototype, 'querySelector')
    act(() => { last(listProps).onJump('execution') })
    act(() => { jest.advanceTimersByTime(250 * 3) })
    expect(looks).toHaveBeenCalledTimes(3)
    act(() => { last(listProps).onJump('permissions') })
    act(() => { jest.advanceTimersByTime(250 * 30) })

    // the first jump looked three times; only the second one's 25 looks follow
    expect(looks).toHaveBeenCalledTimes(3 + 25)
    expect(scrollIntoView).not.toHaveBeenCalled()
    expect(jest.getTimerCount()).toBe(0)
  })
})
