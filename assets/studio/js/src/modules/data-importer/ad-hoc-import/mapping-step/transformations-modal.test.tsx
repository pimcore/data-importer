/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */
import React from 'react'
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type BundleDataImporterAdHocImportTransformationResponse } from '../../data-importer-api-slice.gen'
import { type MappingField, type MappingRow } from './mapping-state'
import { TransformationsModal, type TransformationSource } from './transformations-modal'

jest.mock('@pimcore/studio-ui-bundle/app', () => jest.requireActual<object>('../../../../../test/support/ad-hoc-import'))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: class { constructor (readonly details: unknown) {} },
  trackError: (...args: unknown[]) => mockTrackError(...args)
}))
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportPreviewTransformationsMutation: () => [mockPreview]
}))

const mockTrackError = jest.fn()
const mockPreview = jest.fn()

type Answer = BundleDataImporterAdHocImportTransformationResponse

const cell = (...items: string[]): Answer['values'][number] => ({ items, failed: false })

const answer = (given: Partial<Answer> = {}): Answer => ({
  values: [cell(' Chair '), cell(), cell('x')],
  steps: [],
  conversion: null,
  resultType: 'default',
  fits: true,
  results: [cell(' Chair '), cell(), cell('x')],
  offered: ['trim', 'stringReplace', 'conditionalConversion'],
  ...given
})

const row: MappingRow = {
  column: 'Name',
  label: 'Name & title',
  choice: { field: 'name', language: null },
  auto: false,
  skipped: false,
  isMatch: false,
  transformations: []
}

const field: MappingField = { key: 'name', title: 'Name <en>', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true }

const source: TransformationSource = { fileId: 'file-1', classId: 'PROD', settings: { delimiter: ';', hasHeaderRow: true } }

const onApply = jest.fn()
const onCancel = jest.fn()

const renderModal = (given: Partial<MappingRow> = {}, withField: MappingField = field): void => {
  render(
    <TransformationsModal
      field={ withField }
      onApply={ onApply }
      onCancel={ onCancel }
      row={ { ...row, ...given } }
      source={ source }
    />
  )
}

// waits out the pause before a preview and lets its answer arrive
const settle = async (): Promise<void> => {
  await act(async () => {
    jest.advanceTimersByTime(300)
    await Promise.resolve()
    await Promise.resolve()
  })
}

const lastPipeline = (): unknown => (mockPreview.mock.calls[mockPreview.mock.calls.length - 1][0] as {
  bundleDataImporterAdHocImportTransformationParameters: { transformationPipeline: unknown }
}).bundleDataImporterAdHocImportTransformationParameters.transformationPipeline

const addStep = (label: string): void => {
  const add = screen.getByLabelText<HTMLSelectElement>('Add a transformation')
  const option = within(add).getByRole<HTMLOptionElement>('option', { name: label })
  fireEvent.change(add, { target: { value: option.value } })
}

const table = (): string[][] => Array.from(document.querySelectorAll('tr'))
  .map((tableRow) => Array.from(tableRow.querySelectorAll('th, td')).map((tableCell) => tableCell.textContent ?? ''))

const applyButton = (): HTMLButtonElement => screen.getByRole<HTMLButtonElement>('button', { name: 'Apply' })

beforeEach(() => {
  jest.useFakeTimers()
  mockPreview.mockReset()
  mockPreview.mockResolvedValue({ data: answer() })
  mockTrackError.mockReset()
  onApply.mockReset()
  onCancel.mockReset()
})

afterEach(() => {
  cleanup()
  jest.useRealTimers()
})

describe('the transformations of a column', () => {
  it('runs on the stored file under its settings, for the column and its field', async () => {
    renderModal()
    await settle()

    expect(mockPreview).toHaveBeenCalledTimes(1)
    expect(mockPreview).toHaveBeenCalledWith({
      fileId: 'file-1',
      bundleDataImporterAdHocImportTransformationParameters: {
        delimiter: ';',
        hasHeaderRow: true,
        classId: 'PROD',
        column: 'Name',
        field: 'name',
        transformationPipeline: []
      }
    })
    expect(screen.getByRole('dialog', { name: 'Transformations of Name & title into Name <en>' })).toBeTruthy()
    expect(screen.getByText('The values go into the field as they are in the file.')).toBeTruthy()
  })

  it('shows each row\'s value in the file, after each step and in the field', async () => {
    mockPreview.mockResolvedValue({
      data: answer({
        steps: [{ type: 'trim', resultType: 'default', error: null, values: [cell('Chair'), cell(), cell('x')] }],
        results: [cell('Chair'), cell(), { items: [], failed: true }],
        conversion: 'numeric'
      })
    })
    renderModal({ transformations: [{ type: 'trim', settings: { mode: 'both' } }] })
    await settle()

    expect(table()).toEqual([
      ['In the file', '1. Trim', 'Into Name <en>'],
      [' Chair ', 'Chair', 'Chair'],
      ['(empty)', '(empty)', '(empty)'],
      ['x', 'x', 'Cannot be converted']
    ])
    expect(screen.getByText('Converted to a number for the field.')).toBeTruthy()
  })

  it('joins the items of a list', async () => {
    mockPreview.mockResolvedValue({ data: answer({ values: [cell('red,blue')], results: [cell('red', 'blue')] }) })
    renderModal()
    await settle()

    expect(table()[1]).toEqual(['red,blue', 'red, blue'])
  })

  it('offers only the transformations the server names, each with its settings', async () => {
    mockPreview.mockResolvedValue({ data: answer({ offered: ['trim', 'loadAsset'] }) })
    renderModal()
    await settle()

    const add = screen.getByLabelText('Add a transformation')
    expect(within(add).getAllByRole('option').map((option) => option.textContent)).toEqual(['Add a transformation', 'Trim', 'Load asset'])

    addStep('Load asset')
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadAsset', settings: { loadStrategy: 'id' } }])
    fireEvent.click(screen.getByRole('radio', { name: 'Full path' }))
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadAsset', settings: { loadStrategy: 'path' } }])
  })

  it('runs the values again once typing pauses', async () => {
    renderModal({ transformations: [{ type: 'stringReplace', settings: { search: '', replace: '' } }] })
    await settle()

    fireEvent.change(screen.getByLabelText('Find'), { target: { value: ',' } })
    fireEvent.change(screen.getByLabelText('Replace with'), { target: { value: '.' } })
    await settle()

    expect(mockPreview).toHaveBeenCalledTimes(2)
    expect(lastPipeline()).toEqual([{ type: 'stringReplace', settings: { search: ',', replace: '.' } }])
  })

  it('takes each transformation\'s settings', async () => {
    renderModal({
      transformations: [
        { type: 'trim', settings: { mode: 'both' } },
        { type: 'conditionalConversion', settings: { original: '', converted: '' } },
        { type: 'explode', settings: { delimiter: ',' } }
      ]
    })
    await settle()

    fireEvent.change(screen.getByLabelText('Remove spaces'), { target: { value: 'right' } })
    fireEvent.change(screen.getByLabelText('Values'), { target: { value: 'M|L' } })
    fireEvent.change(screen.getByLabelText('Into'), { target: { value: 'Medium|Large' } })
    fireEvent.change(screen.getByLabelText('Separator'), { target: { value: '|' } })
    await settle()

    expect(lastPipeline()).toEqual([
      { type: 'trim', settings: { mode: 'right' } },
      { type: 'conditionalConversion', settings: { original: 'M|L', converted: 'Medium|Large' } },
      { type: 'explode', settings: { delimiter: '|' } }
    ])
    expect(screen.getByText('Separate the values with |, in the same order in both; * stands for any other value.')).toBeTruthy()
  })

  it('removes a transformation', async () => {
    renderModal({ transformations: [{ type: 'trim', settings: { mode: 'both' } }, { type: 'loadDataObject', settings: { loadStrategy: 'id' } }] })
    await settle()

    fireEvent.click(screen.getByRole('button', { name: 'Remove Trim' }))
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadDataObject', settings: { loadStrategy: 'id' } }])
    expect(within(screen.getByRole('list', { name: 'Transformations' })).getAllByRole('listitem')).toHaveLength(1)
  })

  it('says when the values do not fit the field and which step cannot take its input', async () => {
    mockPreview.mockResolvedValue({
      data: answer({
        steps: [
          { type: 'loadAsset', resultType: 'asset', error: null, values: [cell('/a.jpg')] },
          { type: 'trim', resultType: null, error: 'Unsupported input type', values: [] }
        ],
        fits: false,
        resultType: null,
        results: [],
        offered: []
      })
    })
    renderModal({ transformations: [{ type: 'loadAsset', settings: { loadStrategy: 'path' } }, { type: 'trim', settings: { mode: 'both' } }] })
    await settle()

    const alerts = screen.getAllByRole('alert').map((alert) => alert.textContent)
    expect(alerts).toEqual([
      'This transformation cannot take the values of the one before it.',
      'The values cannot go into Name <en> yet. Add a transformation that leads there, or choose another field.'
    ])
    expect(screen.getByLabelText<HTMLSelectElement>('Add a transformation').disabled).toBe(true)
  })

  it('says when the lists of map values differ in length', async () => {
    mockPreview.mockResolvedValue({
      data: answer({
        steps: [{ type: 'conditionalConversion', resultType: null, error: 'differ in length', values: [] }],
        fits: false,
        resultType: null,
        results: [],
        offered: []
      })
    })
    renderModal({ transformations: [{ type: 'conditionalConversion', settings: { original: 'S|M|L', converted: 'small|medium' } }] })
    await settle()

    expect(screen.getAllByRole('alert')[0].textContent).toBe('Give as many values under Into as under Values.')
  })

  it('applies the transformations with the server\'s answer whether they fit', async () => {
    mockPreview.mockResolvedValue({ data: answer({ fits: false }) })
    renderModal()

    expect(applyButton().disabled).toBe(true)
    await settle()

    addStep('Trim')
    expect(applyButton().disabled).toBe(true)
    await settle()

    fireEvent.click(applyButton())
    expect(onApply).toHaveBeenCalledWith([{ type: 'trim', settings: { mode: 'both' } }], false)
  })

  it('takes only the answer to the steps asked for last', async () => {
    let late: (value: unknown) => void = () => {}
    mockPreview.mockResolvedValue({ data: answer({ offered: ['trim'] }) })
    renderModal()
    await settle()

    mockPreview.mockReturnValueOnce(new Promise((resolve) => { late = resolve }))
    addStep('Trim')
    await settle()
    fireEvent.change(screen.getByLabelText('Remove spaces'), { target: { value: 'left' } })
    await settle()

    await act(async () => { late({ data: answer({ offered: ['loadAsset'], fits: false }) }) })

    expect(mockPreview).toHaveBeenCalledTimes(3)
    expect(within(screen.getByLabelText('Add a transformation')).getAllByRole('option').map((option) => option.textContent))
      .toEqual(['Add a transformation', 'Trim'])
    fireEvent.click(applyButton())
    expect(onApply).toHaveBeenCalledWith([{ type: 'trim', settings: { mode: 'left' } }], true)
  })

  it('reports a failed request and applies nothing', async () => {
    mockPreview.mockResolvedValue({ error: { status: 422, data: { message: 'no' } } })
    renderModal()
    await settle()

    expect(mockTrackError).toHaveBeenCalledTimes(1)
    expect(applyButton().disabled).toBe(true)
  })

  it('closes without applying', async () => {
    renderModal()
    await settle()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalledTimes(1)
    expect(onApply).not.toHaveBeenCalled()
  })
})
