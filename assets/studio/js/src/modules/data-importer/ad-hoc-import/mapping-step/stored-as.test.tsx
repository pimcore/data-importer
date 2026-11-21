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
import { type MappingField, type MappingRow } from './mapping-state'
import { MappingRowView } from './mapping-row'
import { type TransformationAnswer, type TransformationSource } from './transformations-editor'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: { value: 'value', failed: 'failed' } })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: class { constructor (readonly details: unknown) {} },
  trackError: jest.fn()
}))
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportPreviewTransformationsMutation: () => [mockPreview]
}))

const mockPreview = jest.fn()

const price: MappingField = { key: 'price', title: 'Price', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: false, group: ['Sales'], classificationStore: false }
const row: MappingRow = { column: 'Preis', label: 'Preis', sample: '1.189,90', choice: { field: 'price', language: null }, auto: true, skipped: false, isMatch: false, transformations: [] }
const source: TransformationSource = { fileId: 'file-1', classId: 'PROD', settings: { hasHeaderRow: true } }

const answer = (given: Partial<TransformationAnswer> = {}): TransformationAnswer => ({
  values: [{ items: [], failed: false }, { items: ['1189.90'], failed: false }],
  steps: [],
  conversion: 'numeric',
  resultType: 'numeric',
  fits: true,
  results: [{ items: [], failed: false }, { items: ['1189.9'], failed: false }],
  offered: [],
  ...given
})

const renderRow = (given: Partial<MappingRow> = {}, withSource: TransformationSource | null = source): void => {
  const shown = { ...row, ...given }
  render(
    <ul>
      <MappingRowView
        fields={ [price] }
        isActive={ false }
        languages={ ['en'] }
        mapping={ { basis: '', rows: [shown] } }
        onChange={ () => {} }
        onOpenField={ () => {} }
        onPickStoreKey={ () => {} }
        onSkip={ () => {} }
        row={ shown }
        source={ withSource }
      />
    </ul>
  )
}

const settle = async (): Promise<void> => {
  await act(async () => {
    jest.advanceTimersByTime(300)
    await Promise.resolve()
    await Promise.resolve()
  })
}

beforeEach(() => {
  jest.useFakeTimers()
  mockPreview.mockReset()
  mockPreview.mockResolvedValue({ data: answer() })
})

afterEach(() => {
  cleanup()
  jest.useRealTimers()
})

describe('what a column is stored as', () => {
  it('shows the first value with one as it goes into the field, and the conversion into its type', async () => {
    renderRow()
    await settle()

    expect(mockPreview).toHaveBeenCalledTimes(1)
    expect(mockPreview.mock.calls[0][0]).toMatchObject({
      fileId: 'file-1',
      bundleDataImporterAdHocImportTransformationParameters: { classId: 'PROD', column: 'Preis', field: 'price', transformationPipeline: [] }
    })
    expect(screen.getByText('1189.9').className).toBe('value')
    expect(screen.getByText('number')).toBeTruthy()
  })

  it('marks a value that does not reach the field', async () => {
    mockPreview.mockResolvedValue({ data: answer({ fits: false, results: [{ items: [], failed: false }, { items: [], failed: true }] }) })
    renderRow()
    await settle()

    expect(screen.getByText('Cannot be converted').className).toBe('value failed')
  })

  it('names no conversion for a value that fits as it is', async () => {
    mockPreview.mockResolvedValue({ data: answer({ conversion: null }) })
    renderRow()
    await settle()

    expect(screen.getByText('1189.9')).toBeTruthy()
    expect(screen.queryByText('number')).toBeNull()
  })

  it.each([
    ['a column without a field', { choice: undefined }],
    ['the column that finds the objects', { isMatch: true }],
    ['a classification store key', { choice: { field: 'price', language: null, key: { id: '1-2', label: 'Weight' } } }]
  ])('asks nothing for %s', async (_, given: Partial<MappingRow>) => {
    renderRow(given)
    await settle()

    expect(mockPreview).not.toHaveBeenCalled()
    expect(screen.queryByText('1189.9')).toBeNull()
  })

  it('asks nothing without a file to run the values on', async () => {
    renderRow({}, null)
    await settle()

    expect(mockPreview).not.toHaveBeenCalled()
  })
})

describe('a column\'s field in the table', () => {
  it('offers its suggestion by name, as the button\'s name says', () => {
    renderRow({ choice: undefined, auto: false, suggestion: { field: 'price', language: null, score: 90 } })

    const offer = screen.getByRole('button', { name: 'Use Price?' })
    expect(offer.querySelector('strong')?.textContent).toBe('Price')
  })

  it('says its field can be changed', () => {
    renderRow()

    expect(screen.getByRole('button', { name: 'Field for Preis' }).getAttribute('title')).toBe('Change field')
  })
})
