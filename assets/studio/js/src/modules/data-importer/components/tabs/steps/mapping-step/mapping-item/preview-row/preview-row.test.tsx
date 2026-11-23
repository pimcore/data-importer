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
import * as gen from '../../../../../../data-importer-api-slice.gen'
import { fail, loaded, MappingList, mappingConfig, mockTransformationQueries, PostedHost, previewRowOf, previewTexts, respond, switchPreviewOn } from '../../../../../../../../../test/support/mapping-preview'

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
jest.mock('../../../../../../data-importer-api-slice-enhanced', () => ({
  api: { endpoints: { bundleDataImporterDataTypeLoadClassAttributes: { initiate: () => ({}) } } }
}))
jest.mock('../../../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterMappingLoadTransformationResultQuery: jest.fn(),
  useBundleDataImporterConfigCalculateTransformationResultTypeQuery: jest.fn(),
  useBundleDataImporterConfigLoadColumnHeadersQuery: jest.fn(),
  useBundleDataImporterConfigLoadPreviewQuery: jest.fn(),
  useBundleDataImporterConfigLoadTransformationResultQuery: jest.fn()
}))
jest.mock('../../../advanced-mapping-modal', () => ({ AdvancedMappingModal: () => null }))
jest.mock('../mapping-drop-zone', () => ({ MappingDropZone: () => null }))
jest.mock('../../sources-panel/sources-panel', () => ({ SourcesPanel: () => null }))
jest.mock('../../source-picker-content/source-picker-content', () => ({ SourcePickerContent: () => null }))
jest.mock('../../autofill-suggestions-panel', () => ({ AutofillSuggestionsPanel: () => null, applySelectedSuggestions: () => [] }))
jest.mock('../../mappings-panel/mappings-panel', () => ({ MappingsPanel: () => <MappingList /> }))

beforeEach(mockTransformationQueries)

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('Preview row warnings and failed previews', () => {
  it('AC3: a mapping in a warning state has the warning icon in its title and the reason in its Preview row', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()
    respond(0, ['Stuhl', 'A-1', 'A-1 Stuhl', 'A-1'])

    const combined = screen.getByTestId('mapping-2')
    const loose = screen.getByTestId('mapping-3')
    expect(combined.querySelector('[data-mapping-warning] [data-icon="warning-circle"]')).not.toBeNull()
    expect(combined.querySelector('[data-mapping-warning]')?.textContent).toBe('Combined')
    expect(loose.querySelector('[data-mapping-warning] [data-icon="warning-circle"]')).not.toBeNull()
    expect(previewRowOf(2).textContent).toBe('data-importer.mapping.preview-mode.previewdata-importer.mapping.preview-mode.warning.advanced-setup')
    expect(previewRowOf(3).textContent).toBe('data-importer.mapping.preview-mode.previewdata-importer.mapping.preview-mode.warning.further-configuration')

    // a mapping with a destination has none
    expect(screen.getByTestId('mapping-0').querySelector('[data-icon="warning-circle"]')).toBeNull()
  })

  it('AC3: the arrow column no longer shows the warning badge, the title does', async () => {
    render(<PostedHost mappings={ [mappingConfig[0], { label: 'Loose', dataSourceIndex: ['sku'] }] } />)
    await loaded()

    // the second mapping is expanded: its arrow column shows, its only warning icon sits in the title
    const icons = screen.getByTestId('mapping-1').querySelectorAll('[data-icon="warning-circle"]')
    expect(icons).toHaveLength(1)
    expect(icons[0].closest('[data-mapping-warning]')).not.toBeNull()
  })

  it('AC3: a failed request makes every row read Preview unavailable and shows the server message once', async () => {
    render(<PostedHost />)
    await loaded()
    await switchPreviewOn()

    fail(0, { status: 500, data: { message: 'Transformer exploded' } })

    await waitFor(() => { expect(screen.getAllByText('Transformer exploded')).toHaveLength(1) })
    for (const text of previewTexts()) {
      expect(text).toBe('data-importer.mapping.preview-mode.previewdata-importer.mapping.preview-mode.unavailable')
    }
  })
})
