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
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { type SourceRow } from './sources-panel/sources-panel'
import { PostedMappingSource } from '../mapping-source/posted-mapping-source'
import { MappingStep } from '.'

const dispatch = jest.fn(async (_action: unknown) => await Promise.resolve({ data: { attributes: [{ key: 'sku', title: 'SKU' }] } }))
const initiate = jest.fn((request: unknown, options?: unknown) => ({ request, options }))
const suggestMappings = jest.fn<Promise<{ data?: { suggestions: unknown[] }, error?: unknown }>, [unknown]>(
  async (_request) => await Promise.resolve({ data: { suggestions: [] } })
)
const trackError = jest.fn()
let isSuggesting = false

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
  useAppDispatch: () => dispatch
}))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: class {
    constructor (readonly details: unknown) {}
  },
  trackError: (error: unknown) => trackError(error),
  useSettings: () => ({ validLanguages: ['en'] })
}))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  FieldWidthProvider: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ uuid: () => crypto.randomUUID() }))
// the factory runs before this file's constants exist, so it reaches initiate lazily
jest.mock('../../../../data-importer-api-slice-enhanced', () => ({
  api: { endpoints: { bundleDataImporterDataTypeLoadClassAttributes: { initiate: (...args: Parameters<typeof initiate>) => initiate(...args) } } },
  useBundleDataImporterMappingSuggestionSuggestMutation: () => [async (request: unknown) => await suggestMappings(request), { isLoading: isSuggesting }],
  useBundleDataImporterMappingSuggestionLoadAiQuery: () => ({ isError: false })
}))
jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterMappingLoadTransformationResultQuery: jest.fn()
}))

// the panels are out of scope; their stand-ins show what the step hands them
jest.mock('./sources-panel/sources-panel', () => ({
  SourcesPanel: ({ sourceRows }: { sourceRows: SourceRow[] }) => (
    <ul>
      { sourceRows.map(row => <li key={ row.dataIndex }>{ `${row.dataIndex}=${row.value}` }</li>) }
    </ul>
  )
}))
jest.mock('./mappings-panel/mappings-panel', () => ({
  MappingsPanel: ({ onOpenAutofillSuggestions }: { onOpenAutofillSuggestions: () => void }) => (
    <button
      onClick={ onOpenAutofillSuggestions }
      type="button"
    >
      autofill
    </button>
  )
}))
jest.mock('./source-picker-content/source-picker-content', () => ({
  SourcePickerContent: () => null
}))
jest.mock('./autofill-suggestions-panel', () => ({
  AutofillSuggestionsPanel: ({ previewRow, suggestions }: { previewRow: Record<string, string | null>, suggestions: unknown[] }) => (
    <>
      <span data-testid="autofill-record">{ JSON.stringify(previewRow) }</span>
      <span data-testid="autofill-suggestions">{ suggestions.length }</span>
    </>
  ),
  applySelectedSuggestions: jest.requireActual('./autofill-suggestions-panel/autofill-suggestions-panel').applySelectedSuggestions
}))

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name [de]' }]
const records = [{ sku: 'A-1', 'name [de]': 'Stuhl' }, { sku: 'A-2', 'name [de]': 'Tisch' }]
const configuration = {
  resolverConfig: { dataObjectClassId: 'Product' },
  mappingConfig: [{ label: 'Price', dataSourceIndex: ['price'], transformationResultType: 'numeric' }]
}

const Host = ({ classId = 'Product', mapped = [] }: { classId?: string, mapped?: Array<{ dataSourceIndex: string[] }> }): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form
      form={ form }
      initialValues={ { mappingConfig: mapped } }
    >
      { /* stands in for the mappings panel's list; the test's antd only watches the list's registered item fields */ }
      <Form.List name="mappingConfig">
        { (fields) => fields.map(field => (
          <Form.Item
            key={ field.key }
            name={ [field.name, 'transformationResultType'] }
            noStyle
          >
            <input type="hidden" />
          </Form.Item>
        )) }
      </Form.List>
      <PostedMappingSource
        columns={ columns }
        configuration={ { ...configuration, resolverConfig: { dataObjectClassId: classId } } }
        id="format"
        records={ records }
      >
        <MappingStep isActive />
      </PostedMappingSource>
    </Form>
  )
}

const clickAutofillOnceLoaded = async (): Promise<void> => {
  await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
  fireEvent.click(screen.getByRole('button', { name: 'autofill' }))
}

afterEach(() => {
  isSuggesting = false
  cleanup()
  jest.clearAllMocks()
})

describe('MappingStep on a posted mapping source', () => {
  it('lists the posted record as its sources', async () => {
    render(<Host />)

    await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
    expect(screen.getAllByRole('listitem').map(item => item.textContent)).toEqual(['sku=A-1', 'name [de]=Stuhl'])
  })

  it('loads the attributes of the posted configuration\'s class', async () => {
    render(<Host />)

    await waitFor(() => { expect(dispatch).toHaveBeenCalled() })
    const requested = initiate.mock.calls.map(([request]) => request as { classId: string, transformationResultType?: string })
    expect(new Set(requested.map(request => request.classId))).toEqual(new Set(['Product']))
    expect(requested.map(request => request.transformationResultType)).toEqual(expect.arrayContaining([undefined, 'numeric']))
  })

  it('suggests mappings against the posted record', async () => {
    render(<Host />)
    await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })

    fireEvent.click(screen.getByRole('button', { name: 'autofill' }))

    const record = await screen.findByTestId('autofill-record')
    expect(JSON.parse(record.textContent ?? '')).toEqual({ sku: 'A-1', 'name [de]': 'Stuhl' })
    expect(suggestMappings).toHaveBeenCalledWith({
      bundleDataImporterMappingSuggestionParameters: {
        classId: 'Product',
        columns: [{ index: 'sku', label: 'SKU' }, { index: 'name [de]', label: 'name [de]' }]
      }
    })
  })

  it('reports a failed request and opens no suggestions', async () => {
    const error = { status: 500 }
    suggestMappings.mockResolvedValueOnce({ error })
    render(<Host />)

    await clickAutofillOnceLoaded()

    await waitFor(() => { expect(trackError).toHaveBeenCalledTimes(1) })
    expect(trackError).toHaveBeenCalledWith(expect.objectContaining({ details: error }))
    expect(screen.queryByTestId('autofill-record')).toBeNull()
  })

  it('sends no second request while one is running', async () => {
    isSuggesting = true
    render(<Host />)

    await clickAutofillOnceLoaded()

    expect(suggestMappings).not.toHaveBeenCalled()
    expect(trackError).not.toHaveBeenCalled()
    expect(screen.queryByTestId('autofill-record')).toBeNull()
  })

  it('asks only for the columns no mapping row reads', async () => {
    render(<Host mapped={ [{ dataSourceIndex: ['sku'] }] } />)

    await clickAutofillOnceLoaded()

    await screen.findByTestId('autofill-record')
    expect(suggestMappings).toHaveBeenCalledWith({
      bundleDataImporterMappingSuggestionParameters: {
        classId: 'Product',
        columns: [{ index: 'name [de]', label: 'name [de]' }]
      }
    })
  })

  it.each([
    ['every column is mapped', { mapped: [{ dataSourceIndex: ['sku'] }, { dataSourceIndex: ['name [de]'] }] }],
    ['the configuration has no class', { classId: '' }]
  ])('opens empty without a request when %s', async (_case, props) => {
    render(<Host { ...props } />)

    await clickAutofillOnceLoaded()

    expect((await screen.findByTestId('autofill-suggestions')).textContent).toBe('0')
    expect(suggestMappings).not.toHaveBeenCalled()
  })

  it('loads the attributes of an applied suggestion\'s result type', async () => {
    suggestMappings.mockResolvedValueOnce({
      data: {
        suggestions: [{
          sourceIndex: 'sku',
          sourceLabel: 'SKU',
          targetFieldName: 'released',
          targetFieldLabel: 'Released [released]',
          transformationResultType: 'date',
          score: 100,
          language: null
        }]
      }
    })
    render(<Host />)

    await clickAutofillOnceLoaded()
    await screen.findByTestId('autofill-record')
    expect(initiate).not.toHaveBeenCalledWith(expect.objectContaining({ transformationResultType: 'date' }))

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.autofill-suggestions.apply' }))

    await waitFor(() => {
      expect(initiate).toHaveBeenCalledWith(
        expect.objectContaining({ classId: 'Product', transformationResultType: 'date', systemWrite: true })
      )
    })
  })
})
