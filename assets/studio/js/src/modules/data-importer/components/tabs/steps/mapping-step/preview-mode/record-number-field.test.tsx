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
import { type BundleDataImporterDataPreviewResponse } from '../../../../../data-importer-api-slice.gen'
import { type SourceRow } from '../sources-panel/sources-panel'
import { type MappingSource, MappingSourceContext, type MappingSourceQuery } from '../../mapping-source/mapping-source'
import { MappingStep } from '..'
import { PREVIEW_REQUEST_DEBOUNCE_MS, useMappingPreviewMode } from './mapping-preview-mode'
import { PreviewModeLine } from './preview-mode-line'
import { RECORD_INPUT_DEBOUNCE_MS } from './record-number-field'

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

// the record the mappings' transformation request runs on
const ContextProbe = (): React.JSX.Element => {
  const { previewRequest } = useMappingPreviewMode()

  return <output data-testid="preview-request">{ JSON.stringify(previewRequest?.recordNumber ?? null) }</output>
}

jest.mock('../sources-panel/sources-panel', () => ({
  SourcesPanel: ({ sourceRows }: { sourceRows: SourceRow[] }) => (
    <ul aria-label="sources">
      { sourceRows.map(row => <li key={ row.dataIndex }>{ `${row.dataIndex}=${row.value}` }</li>) }
    </ul>
  )
}))
jest.mock('../mappings-panel/mappings-panel', () => ({
  MappingsPanel: () => (
    <>
      <PreviewModeLine
        allCollapsed={ false }
        onCollapseAll={ () => undefined }
        showCollapseAll
      />
      <ContextProbe />
    </>
  )
}))
jest.mock('../source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('../autofill-suggestions-panel', () => ({ AutofillSuggestionsPanel: () => null, applySelectedSuggestions: () => [] }))

const settled = <T, >(data: T | undefined): MappingSourceQuery<T> => ({
  data,
  isLoading: false,
  isFetching: false,
  isError: false,
  isSuccess: data !== undefined,
  refetch: async () => undefined
})

// a stored configuration's preview file of 150 records, read through load-preview as RTK Query does: one request per
// record number not asked before; a record past the last one is answered with the last one, as the interpreters do
const RECORD_COUNT = 150
const asked: number[] = []
const answers = new Map<number, BundleDataImporterDataPreviewResponse>()
const answerFor = (recordNumber: number): BundleDataImporterDataPreviewResponse => {
  let answer = answers.get(recordNumber)
  if (answer === undefined) {
    asked.push(recordNumber)
    const index = Math.min(recordNumber, RECORD_COUNT - 1)
    answer = {
      previewRecordIndex: index,
      dataPreview: [
        { dataIndex: 'name', label: 'Name', data: `Product ${index + 1}` },
        { dataIndex: 'sku', label: 'SKU', data: `SKU-${index + 1}` }
      ]
    }
    answers.set(recordNumber, answer)
  }

  return answer
}

const columnHeaders = { columnHeaders: [{ id: 'name', dataIndex: 'name', label: 'Name' }, { id: 'sku', dataIndex: 'sku', label: 'SKU' }] }
const storedSource: MappingSource = {
  id: 'stored',
  configuration: { resolverConfig: { dataObjectClassId: 'Product' }, mappingConfig: [] },
  isConfigurationLoaded: true,
  revision: '1',
  useColumnHeadersQuery: (request, options) => settled(options?.skip === true || request === undefined ? undefined : columnHeaders),
  usePreviewQuery: (request, options) => settled(options?.skip === true || request === undefined ? undefined : answerFor(request.recordNumber ?? 0)),
  useTransformationResultTypeQuery: () => settled<never>(undefined),
  useTransformationResultQuery: () => settled<never>(undefined)
}

const StoredHost = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form form={ form }>
      <MappingSourceContext.Provider value={ storedSource }>
        <MappingStep isActive />
      </MappingSourceContext.Provider>
    </Form>
  )
}

const wait = async (ms: number): Promise<void> => {
  await act(async () => { await new Promise(resolve => setTimeout(resolve, ms)) })
}

// the typed record has settled and its transformation request is out
const afterSettle = async (): Promise<void> => { await wait(RECORD_INPUT_DEBOUNCE_MS + PREVIEW_REQUEST_DEBOUNCE_MS + 100) }

const field = (): HTMLInputElement => screen.getByRole('textbox', { name: 'data-importer.mapping.preview-mode.record-label' })
const previous = (): HTMLButtonElement => screen.getByRole('button', { name: 'chevron-left' })
const next = (): HTMLButtonElement => screen.getByRole('button', { name: 'chevron-right' })
const shownValue = (): string | null => document.querySelector('[data-record-value]')?.textContent ?? null
const askedSince = (count: number): number[] => asked.slice(count)

const openPreview = async (): Promise<void> => {
  render(<StoredHost />)
  await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
  fireEvent.click(screen.getByRole('switch', { name: 'data-importer.mapping.preview-mode.switch' }))
  await waitFor(() => { expect(shownValue()).toBe('Product 1') })
  await afterSettle()
}

beforeEach(() => {
  asked.length = 0
  answers.clear()
})

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Record number field in the preview line', () => {
  it('AC1: typing 20 shows record 20 after one load-preview request', async () => {
    await openPreview()
    expect(field().value).toBe('1')
    const before = asked.length

    fireEvent.change(field(), { target: { value: '20' } })
    await afterSettle()

    expect(askedSince(before)).toEqual([19])
    expect(field().value).toBe('20')
    expect(shownValue()).toBe('Product 20')
    expect(screen.getByRole('tooltip').textContent).toBe('Product 20')
    // the "Sources with preview" panel and the mappings' request follow the chosen record
    expect(screen.getByRole('list', { name: 'sources' }).textContent).toContain('name=Product 20')
    expect(screen.getByTestId('preview-request').textContent).toBe('19')
  })

  it('AC1: Enter loads the typed record at once, without waiting for the pause', async () => {
    await openPreview()
    const before = asked.length

    fireEvent.change(field(), { target: { value: '7' } })
    fireEvent.keyDown(field(), { key: 'Enter' })

    await waitFor(() => { expect(shownValue()).toBe('Product 7') })
    expect(askedSince(before)).toEqual([6])
  })

  it('AC1: Preview item puts the cursor into the record field', async () => {
    await openPreview()

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.preview-mode.preview-item' }))

    expect(document.activeElement).toBe(field())
  })

  it('AC2: typing quickly sends one request, for the number typed last', async () => {
    await openPreview()
    const before = asked.length

    fireEvent.change(field(), { target: { value: '2' } })
    await wait(100)
    fireEvent.change(field(), { target: { value: '20' } })
    await wait(100)
    fireEvent.change(field(), { target: { value: '205' } })
    await wait(100)
    fireEvent.change(field(), { target: { value: '20' } })
    await afterSettle()

    expect(askedSince(before)).toEqual([19])
    expect(shownValue()).toBe('Product 20')
  })

  it('AC2: clicking next and previous repeatedly sends one request, for the record they land on', async () => {
    await openPreview()
    expect(previous().disabled).toBe(true)
    const before = asked.length

    for (let click = 0; click < 5; click++) fireEvent.click(next())
    fireEvent.click(previous())
    expect(field().value).toBe('5')
    await afterSettle()

    expect(askedSince(before)).toEqual([4])
    expect(shownValue()).toBe('Product 5')
    expect(screen.getByTestId('preview-request').textContent).toBe('4')

    fireEvent.click(previous())
    await afterSettle()
    expect(askedSince(before)).toEqual([4, 3])
    expect(shownValue()).toBe('Product 4')
  })

  it('AC3: 0 or an empty field is refused with the error style and sends no request', async () => {
    await openPreview()
    const before = asked.length

    fireEvent.change(field(), { target: { value: '0' } })
    expect(field().getAttribute('aria-invalid')).toBe('true')
    await afterSettle()
    expect(askedSince(before)).toEqual([])

    fireEvent.change(field(), { target: { value: '' } })
    expect(field().getAttribute('aria-invalid')).toBe('true')
    fireEvent.blur(field())

    // leaving the field puts the selected record back
    expect(field().value).toBe('1')
    expect(field().getAttribute('aria-invalid')).toBe('false')
    await afterSettle()
    expect(askedSince(before)).toEqual([])
    expect(shownValue()).toBe('Product 1')
  })

  it('AC3: a number past the last record lands on the last one, which then shows "of N" and bounds the field', async () => {
    await openPreview()
    expect(screen.queryByText(/data-importer\.mapping\.preview-mode\.of-records/)).toBeNull()

    fireEvent.change(field(), { target: { value: '500' } })
    await afterSettle()

    await waitFor(() => { expect(field().value).toBe('150') })
    expect(shownValue()).toBe('Product 150')
    expect(screen.getByText('data-importer.mapping.preview-mode.of-records(150)')).toBeTruthy()
    expect(next().disabled).toBe(true)
    await afterSettle()
    expect(screen.getByTestId('preview-request').textContent).toBe('149')

    // once the number of records is known, a larger number is refused while typed and kept to it on Enter
    const before = asked.length
    fireEvent.change(field(), { target: { value: '151' } })
    expect(field().getAttribute('aria-invalid')).toBe('true')
    fireEvent.keyDown(field(), { key: 'Enter' })
    expect(field().value).toBe('150')
    await afterSettle()
    expect(askedSince(before)).toEqual([])
  })
})
