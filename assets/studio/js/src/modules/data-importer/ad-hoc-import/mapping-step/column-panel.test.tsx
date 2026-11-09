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
import { act, cleanup, render, screen, within } from '@testing-library/react'
import { ColumnPanel, type ColumnPanelProps } from './column-panel'
import { type MappingField, type MappingRow } from './mapping-state'
import { type TransformationAnswer, type TransformationSource } from './transformations-editor'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: { chipFailed: 'failed', chipResult: 'result' } })
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

const field: MappingField = { key: 'name', title: 'Name', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true, group: ['Product'], classificationStore: false }
const row: MappingRow = { column: 'Bezeichnung', label: 'Bezeichnung', sample: 'Jacke', choice: { field: 'name', language: 'de' }, auto: false, skipped: false, isMatch: false, transformations: [{ type: 'trim', settings: { mode: 'both' } }], fits: undefined }
const source: TransformationSource = { fileId: 'file-1', classId: 'PROD', settings: { hasHeaderRow: true } }

const answer = (given: Partial<TransformationAnswer> = {}): TransformationAnswer => ({
  values: [{ items: ['Jacke'], failed: false }],
  steps: [{ type: 'trim', resultType: 'default', error: null, values: [{ items: ['Jacke'], failed: false }] }],
  conversion: null,
  resultType: 'default',
  fits: true,
  results: [{ items: ['Jacke'], failed: false }],
  offered: ['trim'],
  ...given
})

const onFits = jest.fn()

const renderPanel = (given: Partial<ColumnPanelProps> = {}): void => {
  render(
    <ColumnPanel
      fields={ [field] }
      languages={ ['de'] }
      mapping={ { basis: '', rows: [row] } }
      onClose={ () => {} }
      onFits={ onFits }
      onNext={ () => {} }
      onPick={ () => {} }
      onPrevious={ () => {} }
      onRemoveField={ () => {} }
      onSkip={ () => {} }
      onSteps={ () => {} }
      onTabChange={ () => {} }
      row={ row }
      source={ source }
      tab="transformations"
      { ...given }
    />
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
  onFits.mockReset()
})

afterEach(() => {
  cleanup()
  jest.useRealTimers()
})

describe('the side panel of a column', () => {
  it('shows the way of its first value into the field, on real values', async () => {
    mockPreview.mockResolvedValue({ data: answer() })
    renderPanel()
    await settle()

    const panel = screen.getByRole('complementary', { name: 'Column Bezeichnung' })
    expect(within(panel).getByText('“Jacke”')).toBeTruthy()
    expect(within(panel).getByText('Name (DE): Jacke').className).toBe('result')
    expect(screen.getByRole('tab', { name: 'Transformations (1)' })).toBeTruthy()
  })

  it('joins the items of a list and shows values that cannot be converted as failed', async () => {
    mockPreview.mockResolvedValue({
      data: answer({ results: [{ items: ['red', 'blue'], failed: false }] })
    })
    renderPanel()
    await settle()

    expect(screen.getByText('Name (DE): red, blue')).toBeTruthy()
    cleanup()

    mockPreview.mockResolvedValue({ data: answer({ results: [{ items: [], failed: true }] }) })
    renderPanel()
    await settle()

    expect(screen.getByText('Name (DE): Cannot be converted').className).toBe('failed')
  })

  it('reports whether the column reaches its field, and only when that changes', async () => {
    mockPreview.mockResolvedValue({ data: answer({ fits: false }) })
    renderPanel()
    await settle()

    expect(onFits).toHaveBeenCalledTimes(1)
    expect(onFits).toHaveBeenCalledWith(row.transformations, false)
    cleanup()
    onFits.mockReset()

    renderPanel({ row: { ...row, fits: false } })
    await settle()

    expect(onFits).not.toHaveBeenCalled()
  })

  it('asks for a field first, and runs nothing without one', async () => {
    renderPanel({ row: { ...row, choice: undefined, transformations: [] } })
    await settle()

    expect(mockPreview).not.toHaveBeenCalled()
    expect(screen.getByText('choose a field below')).toBeTruthy()
    expect(screen.getByText('Choose a field first.')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Remove field' })).toBeNull()
  })

  it('has no transformations without a file to run them on', () => {
    renderPanel({ source: null })

    expect(screen.getByRole<HTMLButtonElement>('tab', { name: 'Transformations (1)' }).disabled).toBe(true)
  })
})
