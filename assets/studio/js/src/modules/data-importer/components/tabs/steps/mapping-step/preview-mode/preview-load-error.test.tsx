/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useState } from 'react'
import { Form } from 'antd'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import * as gen from '../../../../../data-importer-api-slice.gen'
import { type MappingSource, MappingSourceContext } from '../../mapping-source/mapping-source'
import { storedConfigQueries } from '../../mapping-source/stored-config-mapping-source'
import { MappingStep } from '..'
import { afterDebounce, loaded, MappingList, mappingConfig, mockTransformationQueries, sent } from '../../../../../../../../test/support/mapping-preview'

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

// how load-preview fails in each test: PreviewDataService's 404 without a preview file, or another failure
const noPreviewData = { status: 404, data: { message: 'No preview data available. Please upload or copy preview data first.' } }
const serverError = { status: 500, data: { message: 'Internal Server Error: the loader threw' } }
const invalidPreviewFile = { status: 400, data: { detail: 'The preview file is not a valid CSV file.' } }
let loadPreviewFailure: object = noPreviewData

// a constant failed answer, as RTK Query keeps it until the next request
const useLoadPreview = (_arg: unknown, options?: { skip?: boolean }): object => {
  const skip = options?.skip === true
  const [answered, setAnswered] = useState(false)
  useEffect(() => {
    if (skip) return
    void Promise.resolve().then(() => { setAnswered(true) })
  }, [skip])

  return {
    data: undefined,
    isLoading: false,
    isFetching: !skip && !answered,
    isError: !skip && answered,
    isSuccess: false,
    error: !skip && answered ? loadPreviewFailure : undefined,
    refetch: async () => { await Promise.resolve() }
  }
}

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
const uploadButton = (): HTMLElement | null => screen.queryByRole('button', { name: 'data-importer.preview-import.upload-file' })

const switchOn = async (): Promise<void> => {
  fireEvent.click(previewSwitch())
  await afterDebounce()
}

beforeEach(() => {
  mockTransformationQueries()
  loadPreviewFailure = noPreviewData
  jest.mocked(gen.useBundleDataImporterConfigLoadPreviewQuery).mockImplementation(useLoadPreview as never)
  jest.mocked(gen.useBundleDataImporterConfigCopyPreviewMutation).mockImplementation((() => [jest.fn(), { isLoading: false }]) as never)
})

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Preview mode when load-preview fails', () => {
  it('AC1: load-preview answering 404 shows the upload/copy hint as before', async () => {
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    expect(previewSwitch().checked).toBe(true)
    expect(hint()).not.toBeNull()
    expect(uploadButton()).not.toBeNull()
    expect(screen.getByRole('button', { name: 'data-importer.preview-import.copy-from-source' })).toBeTruthy()
    expect(screen.queryByText(noPreviewData.data.message)).toBeNull()
  })

  it('AC2: load-preview failing with 500 shows the server error instead of the hint', async () => {
    loadPreviewFailure = serverError
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    // preview mode stays on and says what went wrong
    expect(previewSwitch().checked).toBe(true)
    expect(screen.getAllByText(serverError.data.message)).toHaveLength(1)
    expect(hint()).toBeNull()
    expect(uploadButton()).toBeNull()
    // without a record no transformation is asked for
    expect(sent).toHaveLength(0)

    // turning it off again hides the error
    fireEvent.click(previewSwitch())
    expect(previewSwitch().checked).toBe(false)
    expect(screen.queryByText(serverError.data.message)).toBeNull()
  })

  it('AC2: an invalid preview file shows its error instead of the hint', async () => {
    loadPreviewFailure = invalidPreviewFile
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    expect(previewSwitch().checked).toBe(true)
    expect(screen.getByText(invalidPreviewFile.data.detail)).toBeTruthy()
    expect(hint()).toBeNull()
  })

  it('AC2: a failure without a message shows "Preview unavailable" instead of the hint', async () => {
    loadPreviewFailure = { status: 'FETCH_ERROR' }
    render(<CarImportHost />)
    await loaded()
    await switchOn()

    expect(screen.getByText('data-importer.mapping.preview-mode.unavailable')).toBeTruthy()
    expect(hint()).toBeNull()
  })
})
