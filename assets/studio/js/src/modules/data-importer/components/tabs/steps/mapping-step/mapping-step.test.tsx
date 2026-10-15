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

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
  useAppDispatch: () => dispatch
}))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: ['en'] }) }))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  FieldWidthProvider: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ uuid: () => crypto.randomUUID() }))
// the factory runs before this file's constants exist, so it reaches initiate lazily
jest.mock('../../../../data-importer-api-slice-enhanced', () => ({
  api: { endpoints: { bundleDataImporterDataTypeLoadClassAttributes: { initiate: (...args: Parameters<typeof initiate>) => initiate(...args) } } }
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
  AutofillSuggestionsPanel: ({ previewRow }: { previewRow: Record<string, string | null> }) => (
    <span data-testid="autofill-record">{ JSON.stringify(previewRow) }</span>
  ),
  applySelectedSuggestions: () => []
}))

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name [de]' }]
const records = [{ sku: 'A-1', 'name [de]': 'Stuhl' }, { sku: 'A-2', 'name [de]': 'Tisch' }]
const configuration = {
  resolverConfig: { dataObjectClassId: 'Product' },
  mappingConfig: [{ label: 'Price', dataSourceIndex: ['price'], transformationResultType: 'numeric' }]
}

const Host = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form form={ form }>
      <PostedMappingSource
        columns={ columns }
        configuration={ configuration }
        id="format"
        records={ records }
      >
        <MappingStep isActive />
      </PostedMappingSource>
    </Form>
  )
}

afterEach(() => {
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

    expect(JSON.parse(screen.getByTestId('autofill-record').textContent ?? '')).toEqual({ sku: 'A-1', 'name [de]': 'Stuhl' })
  })
})
