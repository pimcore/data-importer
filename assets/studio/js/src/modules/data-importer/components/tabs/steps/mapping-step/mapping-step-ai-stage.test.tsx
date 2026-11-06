/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useContext, useMemo } from 'react'
import { Form } from 'antd'
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MappingSourceContext } from '../mapping-source/mapping-source'
import { PostedMappingSource } from '../mapping-source/posted-mapping-source'
import { MappingStep } from '.'

const dispatch = jest.fn(async (_action: unknown) => await Promise.resolve({ data: { attributes: [{ key: 'sku', title: 'SKU' }] } }))
const initiate = jest.fn((request: unknown, options?: unknown) => ({ request, options }))
const suggestMappings = jest.fn<Promise<{ data?: { suggestions: unknown[], aiStatus?: string, aiRequestId?: string | null } }>, [unknown]>(
  async (_request) => await Promise.resolve({ data: { suggestions: [] } })
)
const loadAi = jest.fn<{ currentData?: { aiStatus: string, suggestions: unknown[] }, isError: boolean }, [unknown, unknown]>(
  () => ({ isError: false })
)
const trackError = jest.fn()

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
  useBundleDataImporterMappingSuggestionSuggestMutation: () => [async (request: unknown) => await suggestMappings(request), { isLoading: false }],
  useBundleDataImporterMappingSuggestionLoadAiQuery: (request: unknown, options: unknown) => loadAi(request, options)
}))
jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterMappingLoadTransformationResultQuery: jest.fn()
}))

// the panels are out of scope; their stand-ins show what the step hands them
jest.mock('./sources-panel/sources-panel', () => ({ SourcesPanel: () => null }))
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
jest.mock('./source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('./autofill-suggestions-panel', () => ({
  AutofillSuggestionsPanel: ({ suggestions, isLoadingAiSuggestions }: { suggestions: unknown[], isLoadingAiSuggestions: boolean }) => (
    <>
      <span data-testid="autofill-ai-loading">{ String(isLoadingAiSuggestions) }</span>
      <span data-testid="autofill-suggestions">{ suggestions.length }</span>
    </>
  ),
  applySelectedSuggestions: jest.requireActual('./autofill-suggestions-panel/autofill-suggestions-panel').applySelectedSuggestions
}))

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name [de]' }]
const records = [{ sku: 'A-1', 'name [de]': 'Stuhl' }, { sku: 'A-2', 'name [de]': 'Tisch' }]
const configuration = { resolverConfig: { dataObjectClassId: 'Product' }, mappingConfig: [] }

const StoredConfig = ({ children }: { children: React.ReactNode }): React.JSX.Element => {
  const source = useContext(MappingSourceContext)
  const value = useMemo(() => source === undefined ? source : { ...source, configName: 'products' }, [source])

  return <MappingSourceContext.Provider value={ value }>{ children }</MappingSourceContext.Provider>
}

const Host = ({ stored = false }: { stored?: boolean }): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form
      form={ form }
      initialValues={ { mappingConfig: [], interpreterConfig: { type: 'csv' } } }
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
        configuration={ configuration }
        id="format"
        records={ records }
      >
        { stored ? <StoredConfig><MappingStep isActive /></StoredConfig> : <MappingStep isActive /> }
      </PostedMappingSource>
    </Form>
  )
}

const clickAutofillOnceLoaded = async (): Promise<void> => {
  await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
  fireEvent.click(screen.getByRole('button', { name: 'autofill' }))
}

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
  loadAi.mockReset()
  loadAi.mockImplementation(() => ({ isError: false }))
})

describe('MappingStep with the AI stage', () => {
  const nameSuggestion = {
    sourceIndex: 'sku',
    sourceLabel: 'SKU',
    targetFieldName: 'sku',
    targetFieldLabel: 'SKU [sku]',
    transformationResultType: 'default',
    score: 100,
    language: null,
    source: 'name'
  }
  const aiSuggestion = {
    sourceIndex: 'name [de]',
    sourceLabel: 'name [de]',
    targetFieldName: 'released',
    targetFieldLabel: 'Released [released]',
    transformationResultType: 'date',
    score: 90,
    language: null,
    source: 'ai'
  }
  const started = { data: { suggestions: [nameSuggestion], aiStatus: 'running', aiRequestId: 'abc' } }
  const lastLoadOptions = (): { skip: boolean, pollingInterval?: number } =>
    loadAi.mock.lastCall?.[1] as { skip: boolean, pollingInterval?: number }

  it('shows the name matches at once and follows the stage while it runs', async () => {
    suggestMappings.mockResolvedValueOnce(started)
    render(<Host />)

    await clickAutofillOnceLoaded()

    expect((await screen.findByTestId('autofill-suggestions')).textContent).toBe('1')
    await waitFor(() => { expect(screen.getByTestId('autofill-ai-loading').textContent).toBe('true') })
    expect(loadAi).toHaveBeenLastCalledWith({ requestId: 'abc' }, { skip: false, pollingInterval: 3000 })
  })

  it('adds the stage\'s suggestions once it finished, selected, and stops following it', async () => {
    suggestMappings.mockResolvedValueOnce(started)
    loadAi.mockImplementation((request) => (request as { requestId: string }).requestId === 'abc'
      ? { currentData: { aiStatus: 'finished', suggestions: [aiSuggestion] }, isError: false }
      : { isError: false })
    render(<Host />)

    await clickAutofillOnceLoaded()

    await waitFor(() => { expect(screen.getByTestId('autofill-suggestions').textContent).toBe('2') })
    expect(screen.getByTestId('autofill-ai-loading').textContent).toBe('false')
    expect(lastLoadOptions().skip).toBe(true)

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.autofill-suggestions.apply' }))

    await waitFor(() => {
      expect(initiate).toHaveBeenCalledWith(expect.objectContaining({ transformationResultType: 'date' }))
    })
  })

  it.each([
    ['fails', { currentData: { aiStatus: 'failed', suggestions: [] }, isError: false }],
    ['cannot be read', { isError: true }]
  ])('keeps the name matches when the stage %s, and reports nothing', async (_case, answer) => {
    suggestMappings.mockResolvedValueOnce(started)
    loadAi.mockImplementation((request) => (request as { requestId: string }).requestId === 'abc' ? answer : { isError: false })
    render(<Host />)

    await clickAutofillOnceLoaded()

    await waitFor(() => { expect(screen.getByTestId('autofill-ai-loading').textContent).toBe('false') })
    expect(screen.getByTestId('autofill-suggestions').textContent).toBe('1')
    expect(trackError).not.toHaveBeenCalled()
  })

  it('stops following the stage when the suggestions are closed', async () => {
    suggestMappings.mockResolvedValueOnce(started)
    render(<Host />)

    await clickAutofillOnceLoaded()
    await waitFor(() => { expect(lastLoadOptions().skip).toBe(false) })

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.autofill-suggestions.reject-all' }))

    await waitFor(() => { expect(lastLoadOptions().skip).toBe(true) })
  })

  it('stops following the stage when the suggestions are applied', async () => {
    suggestMappings.mockResolvedValueOnce(started)
    render(<Host />)

    await clickAutofillOnceLoaded()
    await waitFor(() => { expect(lastLoadOptions().skip).toBe(false) })

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.autofill-suggestions.apply' }))

    await waitFor(() => { expect(lastLoadOptions().skip).toBe(true) })
  })

  it('follows nothing when the stage is off', async () => {
    suggestMappings.mockResolvedValueOnce({ data: { suggestions: [nameSuggestion], aiStatus: 'none', aiRequestId: null } })
    render(<Host />)

    await clickAutofillOnceLoaded()

    expect((await screen.findByTestId('autofill-ai-loading')).textContent).toBe('false')
    expect(lastLoadOptions().skip).toBe(true)
  })

  it('names a stored configuration, so the server can draw sample values from its preview file', async () => {
    render(<Host stored />)

    await clickAutofillOnceLoaded()

    await waitFor(() => { expect(suggestMappings).toHaveBeenCalledTimes(1) })
    expect(suggestMappings.mock.calls[0][0]).toEqual({
      bundleDataImporterMappingSuggestionParameters: expect.objectContaining({
        configName: 'products',
        currentConfig: expect.objectContaining({ interpreterConfig: expect.anything() })
      })
    })
  })
})
