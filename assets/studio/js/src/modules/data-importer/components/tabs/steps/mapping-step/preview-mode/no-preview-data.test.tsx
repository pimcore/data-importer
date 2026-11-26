/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useState } from 'react'
import { Form } from 'antd'
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import * as gen from '../../../../../data-importer-api-slice.gen'
import { trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { type MappingSource, MappingSourceContext } from '../../mapping-source/mapping-source'
import { storedConfigQueries } from '../../mapping-source/stored-config-mapping-source'
import { MappingStep } from '..'
import { afterDebounce, columns, fail, loaded, MappingList, mappingConfig, mockTransformationQueries, previewTexts, records, respond, sent } from '../../../../../../../../test/support/mapping-preview'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => options === undefined ? key : `${key}(${Object.values(options).join(',')})`
  }),
  useAppDispatch: () => async () => await Promise.resolve({ data: { attributes: [] } })
}))
jest.mock('@pimcore/studio-ui-bundle/api', () => ({ getPrefix: () => '/pimcore-studio/api' }))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  useSettings: () => ({ validLanguages: ['en'] }),
  ApiError: class ApiError { constructor (public readonly body: unknown) {} },
  trackError: jest.fn()
}))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  FieldWidthProvider: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ uuid: () => crypto.randomUUID() }))
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
  useBundleDataImporterConfigLoadTransformationResultQuery: jest.fn(),
  useBundleDataImporterConfigCopyPreviewMutation: jest.fn()
}))
jest.mock('../../advanced-mapping-modal', () => ({ AdvancedMappingModal: () => null }))
jest.mock('../mapping-item/mapping-drop-zone', () => ({ MappingDropZone: () => null }))
jest.mock('../sources-panel/sources-panel', () => ({ SourcesPanel: () => null }))
jest.mock('../source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('../autofill-suggestions-panel', () => ({ AutofillSuggestionsPanel: () => null, applySelectedSuggestions: () => [] }))
jest.mock('../mappings-panel/mappings-panel', () => ({ MappingsPanel: () => <MappingList /> }))

// what PreviewDataService answers while no preview file was uploaded or copied
const noPreviewData = { status: 404, data: { message: 'No preview data available. Please upload or copy preview data first.' } }

// the configuration's preview file: missing until a test uploads or copies one
let previewFileStored = false

// constant answers, as a query hook returns the same data until it refetches
const storedPreviews = records.map((record, index) => ({
  previewRecordIndex: index,
  dataPreview: columns.map(column => ({ dataIndex: column.dataIndex, label: column.label, data: record[column.dataIndex as 'sku' | 'name'] }))
}))

// load-preview as RTK Query keeps it: one cache entry per record, shared by every hook asking for it;
// a failed entry is asked again when it is subscribed to anew and keeps its error until the new answer
const answers = new Map<number, { stored: boolean }>()

const useLoadPreview = (arg: { bundleDataImporterLoadPreviewParameters: { recordNumber?: number } }, options?: { skip?: boolean }): object => {
  const skip = options?.skip === true
  const recordNumber = arg.bundleDataImporterLoadPreviewParameters.recordNumber ?? 0
  const [answer, setAnswer] = useState<{ stored: boolean } | undefined>(() => answers.get(recordNumber))
  const [fetching, setFetching] = useState(false)
  const fetchPreview = useCallback(async (): Promise<void> => {
    setFetching(true)
    await Promise.resolve()
    const next = { stored: previewFileStored }
    answers.set(recordNumber, next)
    setAnswer(next)
    setFetching(false)
  }, [recordNumber])

  useEffect(() => {
    if (skip) return
    const cached = answers.get(recordNumber)
    setAnswer(cached)
    if (cached?.stored !== true) void fetchPreview()
  }, [skip, fetchPreview])

  return {
    data: !skip && answer?.stored === true ? storedPreviews[recordNumber] : undefined,
    isLoading: false,
    isFetching: !skip && (answer === undefined || fetching),
    isError: !skip && !fetching && answer?.stored === false,
    isSuccess: !skip && !fetching && answer?.stored === true,
    error: !skip && answer?.stored === false ? noPreviewData : undefined,
    refetch: fetchPreview
  }
}

const copyPreview = jest.fn(async () => {
  previewFileStored = true
  return await Promise.resolve({ data: {} })
})

const configuration = { resolverConfig: { dataObjectClassId: 'Product' }, mappingConfig }
const carImport: MappingSource = {
  id: 'car-import',
  configName: 'car-import',
  configuration,
  isConfigurationLoaded: true,
  revision: '1',
  ...storedConfigQueries('car-import')
}

const CarImportHost = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form
      form={ form }
      initialValues={ { mappingConfig } }
    >
      <MappingSourceContext.Provider value={ carImport }>
        <MappingStep isActive />
      </MappingSourceContext.Provider>
    </Form>
  )
}

const previewSwitch = (): HTMLInputElement => screen.getByRole('switch', { name: 'data-importer.mapping.preview-mode.switch' })
const hint = (): HTMLElement | null => screen.queryByText('data-importer.mapping.preview-mode.no-preview-data')
const previewRows = (): HTMLElement[] => screen.queryAllByRole('group', { name: 'data-importer.mapping.preview-mode.preview' })

const switchOn = async (): Promise<void> => {
  fireEvent.click(previewSwitch())
  await afterDebounce()
}

beforeEach(() => {
  mockTransformationQueries()
  previewFileStored = false
  answers.clear()
  jest.mocked(gen.useBundleDataImporterConfigLoadPreviewQuery).mockImplementation(useLoadPreview as never)
  jest.mocked(gen.useBundleDataImporterConfigCopyPreviewMutation).mockImplementation((() => [copyPreview, { isLoading: false }]) as never)
})

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Preview mode without preview data', () => {
  it('AC1: without a preview file (404) preview mode shows the upload/copy hint and no error', async () => {
    render(<CarImportHost />)
    await loaded()

    // the toggle stays usable
    expect(previewSwitch().disabled).toBe(false)
    await switchOn()

    expect(previewSwitch().checked).toBe(true)
    expect(hint()).not.toBeNull()
    expect(screen.getAllByText('data-importer.mapping.preview-mode.no-preview-data')).toHaveLength(1)
    expect(screen.getByRole('button', { name: 'data-importer.preview-import.upload-file' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'data-importer.preview-import.copy-from-source' })).toBeTruthy()
    // no error: no red rows, no server message, no toast, no request that could fail
    expect(previewRows()).toHaveLength(0)
    expect(screen.queryByText('data-importer.mapping.preview-mode.unavailable')).toBeNull()
    expect(screen.queryByText(noPreviewData.data.message)).toBeNull()
    expect(trackError).not.toHaveBeenCalled()
    expect(sent).toHaveLength(0)

    // turning it off again hides the hint
    fireEvent.click(previewSwitch())
    expect(previewSwitch().checked).toBe(false)
    expect(hint()).toBeNull()
  })

  it('AC1: a 404 from the transformation result shows the hint, not "Preview unavailable"', async () => {
    previewFileStored = true
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    expect(sent).toHaveLength(1)
    fail(0, noPreviewData)

    await waitFor(() => { expect(hint()).not.toBeNull() })
    expect(previewSwitch().checked).toBe(true)
    expect(previewRows()).toHaveLength(0)
    expect(screen.queryByText('data-importer.mapping.preview-mode.unavailable')).toBeNull()
    expect(screen.queryByText(noPreviewData.data.message)).toBeNull()
  })

  it('AC2: after Copy from data source each mapping shows its result without leaving preview mode', async () => {
    render(<CarImportHost />)
    await loaded()
    await switchOn()
    expect(hint()).not.toBeNull()

    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'data-importer.preview-import.copy-from-source' })) })
    expect(copyPreview).toHaveBeenCalledWith(expect.objectContaining({ name: 'car-import' }))

    await waitFor(() => { expect(hint()).toBeNull() })
    await afterDebounce()
    expect(previewSwitch().checked).toBe(true)
    expect(sent).toHaveLength(1)
    respond(0, ['Stuhl', 'A-1', 'A-1 Stuhl', 'A-1'])

    expect(previewTexts()[0]).toContain('Stuhl')
    expect(previewTexts()[1]).toContain('A-1')
    expect(previewTexts().join()).not.toContain('data-importer.mapping.preview-mode.unavailable')
  })

  it('AC2: after an upload the preview loads by itself in preview mode', async () => {
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    fireEvent.click(screen.getByRole('button', { name: 'data-importer.preview-import.upload-file' }))
    const upload = screen.getByRole('dialog', { name: 'data-importer.preview-import.upload-file' })
    expect(upload).toBeTruthy()
    previewFileStored = true
    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'finish upload' })) })

    await waitFor(() => { expect(hint()).toBeNull() })
    expect(screen.queryByRole('dialog', { name: 'data-importer.preview-import.upload-file' })).toBeNull()
    await afterDebounce()
    expect(previewSwitch().checked).toBe(true)
    expect(sent).toHaveLength(1)
    respond(0, ['Stuhl', 'A-1', 'A-1 Stuhl', 'A-1'])

    expect(previewTexts()[0]).toContain('Stuhl')
  })

  it('AC3: a failed preview request (500) keeps "Preview unavailable" in every row and the message once', async () => {
    previewFileStored = true
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    fail(0, { status: 500, data: { message: 'The transformer threw' } })

    for (const text of previewTexts()) {
      expect(text).toContain('data-importer.mapping.preview-mode.unavailable')
    }
    expect(screen.getAllByText('The transformer threw')).toHaveLength(1)
    expect(hint()).toBeNull()
  })
})
