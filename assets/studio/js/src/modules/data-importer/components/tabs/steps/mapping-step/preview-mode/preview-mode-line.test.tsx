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
import { Form } from 'antd'
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { type SourceRow } from '../sources-panel/sources-panel'
import { PostedMappingSource } from '../../mapping-source/posted-mapping-source'
import { type MappingSource, MappingSourceContext, type MappingSourceQuery } from '../../mapping-source/mapping-source'
import { MappingStep } from '..'
import { PREVIEW_REQUEST_DEBOUNCE_MS, useMappingPreviewMode } from './mapping-preview-mode'
import { PreviewModeLine } from './preview-mode-line'

const collapseAll = jest.fn()

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => options === undefined ? key : `${key}(${Object.values(options).join(',')})`
  }),
  useAppDispatch: () => async () => await Promise.resolve({ data: { attributes: [] } })
}))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: ['en'] }) }))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  FieldWidthProvider: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ uuid: () => crypto.randomUUID() }))
jest.mock('../../../../../data-importer-api-slice-enhanced', () => ({
  api: { endpoints: { bundleDataImporterDataTypeLoadClassAttributes: { initiate: () => ({}) } } }
}))
jest.mock('../../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterMappingLoadTransformationResultQuery: jest.fn(),
  useBundleDataImporterConfigCopyPreviewMutation: () => [async () => ({ data: {} }), { isLoading: false }]
}))

// what the context holds, and a way to set its error as F3's rows do
const ContextProbe = (): React.JSX.Element => {
  const { previewRequest, recordNumber, setPreviewError } = useMappingPreviewMode()

  return (
    <>
      <output data-testid="preview-request">{ JSON.stringify(previewRequest) }</output>
      <output data-testid="record-number">{ recordNumber }</output>
      <button
        onClick={ () => { setPreviewError('The mapping could not be run') } }
        type="button"
      >
        set error
      </button>
    </>
  )
}

jest.mock('../sources-panel/sources-panel', () => ({
  SourcesPanel: ({ sourceRows }: { sourceRows: SourceRow[] }) => (
    <ul aria-label="sources">
      { sourceRows.map(row => <li key={ row.dataIndex }>{ `${row.dataIndex}=${row.value}` }</li>) }
    </ul>
  )
}))
// the panel's own rendering is covered by mappings-panel-content.test.tsx; here it shows the line in the real step
jest.mock('../mappings-panel/mappings-panel', () => ({
  MappingsPanel: () => (
    <>
      <PreviewModeLine
        allCollapsed={ false }
        onCollapseAll={ collapseAll }
        showCollapseAll
      />
      <ContextProbe />
    </>
  )
}))
jest.mock('../source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('../autofill-suggestions-panel', () => ({ AutofillSuggestionsPanel: () => null, applySelectedSuggestions: () => [] }))

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name', label: 'Name' }]
const records = [
  { sku: 'A-1 with a long article number', name: 'Stuhl' },
  { sku: 'A-2', name: 'Tisch' }
]
const configuration = {
  resolverConfig: { dataObjectClassId: 'Product' },
  mappingConfig: [{ label: 'Name', dataSourceIndex: ['name'], transformationResultType: 'input' }]
}

const PostedHost = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form form={ form }>
      <PostedMappingSource
        columns={ columns }
        configuration={ configuration }
        id="posted"
        records={ records }
      >
        <MappingStep isActive />
      </PostedMappingSource>
    </Form>
  )
}

const settled = <T, >(data: T | undefined): MappingSourceQuery<T> => ({
  data,
  isLoading: false,
  isFetching: false,
  isError: false,
  isSuccess: data !== undefined,
  refetch: async () => undefined
})

// a stored configuration whose preview file was never uploaded: the preview has no records
// (constant responses, as a query hook returns the same data until it refetches)
const noColumnHeaders = { columnHeaders: [] }
const noRecords = { dataPreview: [], previewRecordIndex: 0 }
const storedWithoutPreviewFile: MappingSource = {
  id: 'stored',
  configuration: { resolverConfig: { dataObjectClassId: 'Product' }, mappingConfig: [] },
  isConfigurationLoaded: true,
  revision: '1',
  useColumnHeadersQuery: (request) => settled(request === undefined ? undefined : noColumnHeaders),
  usePreviewQuery: (request) => settled(request === undefined ? undefined : noRecords),
  useTransformationResultTypeQuery: () => settled<never>(undefined),
  useTransformationResultQuery: () => settled<never>(undefined)
}

const StoredHost = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form form={ form }>
      <MappingSourceContext.Provider value={ storedWithoutPreviewFile }>
        <MappingStep isActive />
      </MappingSourceContext.Provider>
    </Form>
  )
}

const recordField = (): HTMLInputElement => screen.getByRole('textbox', { name: 'data-importer.mapping.preview-mode.record-label' })

const previewSwitch = (): HTMLInputElement => screen.getByRole('switch', { name: 'data-importer.mapping.preview-mode.switch' })

const loaded = async (): Promise<void> => {
  await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
}

const afterDebounce = async (): Promise<void> => {
  await act(async () => { await new Promise(resolve => setTimeout(resolve, PREVIEW_REQUEST_DEBOUNCE_MS + 100)) })
}

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Preview mode line', () => {
  it('AC1: the line shows the Preview mode toggle off and Collapse all, and sends no preview request', async () => {
    render(<PostedHost />)
    await loaded()

    expect(previewSwitch().checked).toBe(false)
    expect(screen.getByText('data-importer.mapping.preview-mode.switch')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'data-importer.mapping.preview-mode.preview-item' })).toBeNull()

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.collapse-all' }))
    expect(collapseAll).toHaveBeenCalledTimes(1)

    await afterDebounce()
    expect(screen.getByTestId('preview-request').textContent).toBe('null')
  })

  it('AC2: without an uploaded preview file the toggle stays usable and turning it on shows the upload hint', async () => {
    render(<StoredHost />)
    await loaded()

    expect(previewSwitch().disabled).toBe(false)
    expect(screen.queryByText('data-importer.mapping.preview-mode.no-preview-data')).toBeNull()

    fireEvent.click(previewSwitch())

    expect(previewSwitch().checked).toBe(true)
    expect(screen.getByText('data-importer.mapping.preview-mode.no-preview-data')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'data-importer.mapping.preview-mode.preview-item' })).toBeNull()
    await afterDebounce()
    expect(screen.getByTestId('preview-request').textContent).toBe('null')
  })

  it('AC2: on a posted mapping source the toggle is enabled and the posted record is selected', async () => {
    render(<PostedHost />)
    await loaded()

    expect(previewSwitch().disabled).toBe(false)
    expect(screen.queryByText('data-importer.mapping.preview-mode.no-preview-data')).toBeNull()

    fireEvent.click(previewSwitch())

    await waitFor(() => { expect(recordField().value).toBe('1') })
    expect(screen.getByTestId('record-number').textContent).toBe('0')
  })

  it('AC3: turning preview mode on shows the selected record, its full value in a tooltip', async () => {
    render(<PostedHost />)
    await loaded()

    fireEvent.click(previewSwitch())

    await waitFor(() => { expect(screen.getByText('A-1 with a long article number', { selector: '[data-record-value]' })).toBeTruthy() })
    expect(recordField().value).toBe('1')
    expect(screen.getByRole('tooltip').textContent).toBe('A-1 with a long article number')

    await afterDebounce()
    const request = JSON.parse(screen.getByTestId('preview-request').textContent ?? 'null')
    expect(request.recordNumber).toBe(0)
    expect(request.currentConfig.mappingConfig).toEqual([expect.objectContaining({ dataSourceIndex: ['name'] })])
  })

  it('AC4: typing another record number selects it in the context and the line', async () => {
    render(<PostedHost />)
    await loaded()
    fireEvent.click(previewSwitch())
    await waitFor(() => { expect(screen.getByText('A-1 with a long article number', { selector: '[data-record-value]' })).toBeTruthy() })

    fireEvent.change(recordField(), { target: { value: '2' } })
    fireEvent.keyDown(recordField(), { key: 'Enter' })

    await waitFor(() => { expect(screen.getByText('A-2', { selector: '[data-record-value]' })).toBeTruthy() })
    expect(recordField().value).toBe('2')
    expect(screen.getByTestId('record-number').textContent).toBe('1')
    // the "Sources with preview" panel shows the same record
    expect(screen.getByRole('list', { name: 'sources' }).textContent).toContain('name=Tisch')

    await afterDebounce()
    expect(JSON.parse(screen.getByTestId('preview-request').textContent ?? 'null').recordNumber).toBe(1)
  })

  it('AC4: a preview error shows once in the line, and turning preview mode off hides the preview part', async () => {
    render(<PostedHost />)
    await loaded()
    fireEvent.click(previewSwitch())
    await screen.findByRole('textbox', { name: 'data-importer.mapping.preview-mode.record-label' })

    fireEvent.click(screen.getByRole('button', { name: 'set error' }))
    expect(screen.getAllByText('The mapping could not be run')).toHaveLength(1)

    fireEvent.click(previewSwitch())

    expect(screen.queryByText('The mapping could not be run')).toBeNull()
    expect(screen.queryByRole('textbox', { name: 'data-importer.mapping.preview-mode.record-label' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'data-importer.mapping.preview-mode.preview-item' })).toBeNull()
    expect(screen.getByRole('button', { name: 'data-importer.mapping.collapse-all' })).toBeTruthy()
    expect(screen.getByTestId('preview-request').textContent).toBe('null')
  })
})
