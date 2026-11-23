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
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import * as gen from '../../../../../data-importer-api-slice.gen'
import { afterDebounce, loaded, MappingList, mappingConfig, mockTransformationQueries, PostedHost, previewRowOf, previewTexts, records, respond, sent, StoredHost, switchPreviewOn } from '../../../../../../../../test/support/mapping-preview'

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
// the mapping row's own building blocks, on top of the shared stand-ins
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  ...jest.requireActual('@pimcore/studio-ui-bundle/components'),
  Droppable: ({ children }: { children: React.ReactNode }) => <div>{ children }</div>,
  useDroppable: () => ({ getStateClasses: () => [] }),
  Icon: ({ value }: { value: string }) => <span data-icon={ value } />,
  Input: ({ value }: { value?: string }) => (
    <input
      readOnly
      value={ value }
    />
  )
}))
jest.mock('../../../../../data-importer-api-slice-enhanced', () => ({
  api: { endpoints: { bundleDataImporterDataTypeLoadClassAttributes: { initiate: () => ({}) } } }
}))
jest.mock('../../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterMappingLoadTransformationResultQuery: jest.fn(),
  useBundleDataImporterConfigCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterConfigLoadColumnHeadersQuery: jest.fn(),
  useBundleDataImporterConfigLoadPreviewQuery: jest.fn(),
  useBundleDataImporterConfigLoadTransformationResultQuery: jest.fn()
}))
jest.mock('../../advanced-mapping-modal', () => ({ AdvancedMappingModal: () => null }))
jest.mock('./mapping-drop-zone', () => ({ MappingDropZone: () => null }))
jest.mock('../sources-panel/sources-panel', () => ({ SourcesPanel: () => null }))
jest.mock('../source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('../autofill-suggestions-panel', () => ({ AutofillSuggestionsPanel: () => null, applySelectedSuggestions: () => [] }))
jest.mock('../mappings-panel/mappings-panel', () => ({ MappingsPanel: () => <MappingList /> }))

beforeEach(mockTransformationQueries)

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Preview row under each mapping', () => {
  it('AC1: on a posted record one request with the form mappings fills every row, collapsed ones and No value included', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()

    expect(sent).toHaveLength(1)
    const body = (sent[0] as { bundleDataImporterTransformationResultParameters: { mappingConfig: unknown[], dataRow: unknown } })
      .bundleDataImporterTransformationResultParameters
    expect(body.dataRow).toEqual(records[0])
    expect(body.mappingConfig).toEqual(mappingConfig.map(mapping => expect.objectContaining({ label: mapping.label, dataSourceIndex: mapping.dataSourceIndex })))

    respond(0, ['Stuhl', '', 'A-1 Stuhl', 'A-1'])

    // the first mapping is collapsed, the second expanded: both show their own result
    expect(previewRowOf(0).textContent).toContain('Stuhl')
    expect(previewRowOf(1).textContent).toBe('data-importer.mapping.preview-mode.previewdata-importer.mapping.preview-mode.no-value')
    expect(previewRowOf(0).getAttribute('aria-busy')).toBe('false')
    expect(sent).toHaveLength(1)
  })

  it('AC1: on a stored configuration one request for the selected record fills every row', async () => {
    render(<StoredHost />)
    await loaded()
    await switchPreviewOn()

    expect(sent).toHaveLength(1)
    const request = sent[0] as { name: string, bundleDataImporterLoadPreviewParameters: { recordNumber: number, currentConfig: { mappingConfig: unknown[] } } }
    expect(request.name).toBe('qa-mapping-preview')
    expect(request.bundleDataImporterLoadPreviewParameters.recordNumber).toBe(0)
    expect(request.bundleDataImporterLoadPreviewParameters.currentConfig.mappingConfig).toHaveLength(mappingConfig.length)

    respond(0, ['Stuhl', 'A-1', '', ''])

    expect(previewRowOf(0).textContent).toContain('Stuhl')
    expect(previewRowOf(1).textContent).toContain('A-1')
  })

  it('AC1: while the list is filtered every row keeps the result of its own form position', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()
    respond(0, ['Stuhl', 'A-1', 'A-1 Stuhl', 'A-1'])

    fireEvent.click(screen.getByRole('button', { name: 'filter by sku' }))
    await afterDebounce()

    expect(previewRowOf(1).textContent).toContain('A-1')
    expect(previewRowOf(1).textContent).not.toContain('Stuhl')
    expect(sent).toHaveLength(1)
  })

  it('AC2: an edit shows loading, sends one debounced request and never shows the older response', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()
    respond(0, ['first Stuhl', 'first A-1', '', ''])
    expect(previewRowOf(0).textContent).toContain('first Stuhl')

    // the form tells its watchers in a macrotask: the click's act scope waits for it, well within the debounce
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'edit first mapping' }))
      await new Promise(resolve => setTimeout(resolve, 20))
    })
    // the edit waits for the debounce: the rows show loading, not the first request's values
    expect(previewRowOf(0).getAttribute('aria-busy')).toBe('true')
    expect(previewTexts().join()).not.toContain('first')
    await afterDebounce()
    expect(sent).toHaveLength(2)
    expect((sent[1] as { bundleDataImporterTransformationResultParameters: { mappingConfig: Array<{ label: string }> } })
      .bundleDataImporterTransformationResultParameters.mappingConfig[0].label).toBe('Name, edited')

    // another record is selected while the edit's request still runs
    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'select second record' })) })
    await afterDebounce()
    expect(sent).toHaveLength(3)

    // the edit's request answers late: its values never show
    respond(1, ['old Stuhl', 'old A-1', '', ''])
    expect(previewRowOf(0).getAttribute('aria-busy')).toBe('true')
    expect(previewTexts().join()).not.toContain('old')

    respond(2, ['new Tisch', 'new A-2', '', ''])
    expect(previewRowOf(0).getAttribute('aria-busy')).toBe('false')
    expect(previewRowOf(0).textContent).toContain('new Tisch')
  })

  it('AC2: selecting another record refreshes the rows with one request for that record', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()
    respond(0, ['Stuhl', 'A-1', '', ''])

    fireEvent.click(screen.getByRole('button', { name: 'select second record' }))
    expect(previewRowOf(0).getAttribute('aria-busy')).toBe('true')
    await afterDebounce()

    expect(sent).toHaveLength(2)
    expect((sent[1] as { bundleDataImporterTransformationResultParameters: { dataRow: unknown } })
      .bundleDataImporterTransformationResultParameters.dataRow).toEqual(records[1])
    respond(1, ['Tisch', 'A-2', '', ''])
    expect(previewRowOf(0).textContent).toContain('Tisch')
  })

  it('AC4: with preview mode off the mappings render without Preview rows and no request is sent', async () => {
    render(<PostedHost />)
    await loaded()
    await afterDebounce()

    expect(screen.queryAllByRole('group', { name: 'data-importer.mapping.preview-mode.preview' })).toHaveLength(0)
    expect(screen.getByTestId('mapping-0').textContent).toContain('Name')
    expect(sent).toHaveLength(0)
    for (const [, options] of jest.mocked(gen.useBundleDataImporterMappingLoadTransformationResultQuery).mock.calls) {
      expect(options?.skip).toBe(true)
    }
  })

  it('AC4: on a stored configuration with preview mode off no request is sent', async () => {
    render(<StoredHost />)
    await loaded()
    await afterDebounce()

    expect(screen.queryAllByRole('group', { name: 'data-importer.mapping.preview-mode.preview' })).toHaveLength(0)
    expect(sent).toHaveLength(0)
    expect(jest.mocked(gen.useBundleDataImporterConfigLoadTransformationResultQuery).mock.calls.every(([, options]) => options?.skip === true)).toBe(true)
  })
})
