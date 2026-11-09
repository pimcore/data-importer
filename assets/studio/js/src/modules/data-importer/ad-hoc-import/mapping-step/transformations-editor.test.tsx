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
import React, { useState } from 'react'
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type BundleDataImporterAdHocImportTransformationResponse } from '../../data-importer-api-slice.gen'
import { type MappingField, type MappingRow } from './mapping-state'
import { TransformationSettings } from './transformation-settings'
import { TransformationsEditor, useTransformationPreview, transformationSourceOf, type TransformationSource } from './transformations-editor'
import { type InspectedFile } from '../file-step/file-step-state'

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

const field: MappingField = { key: 'name', title: 'Name <en>', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true, group: ['Product'], classificationStore: false }

const source: TransformationSource = { fileId: 'file-1', classId: 'PROD', settings: { delimiter: ';', hasHeaderRow: true } }

const Harness = ({ given, withField }: { given: Partial<MappingRow>, withField: MappingField }): React.JSX.Element => {
  const [steps, setSteps] = useState(given.transformations ?? [])
  const preview = useTransformationPreview({ row: { ...row, ...given }, field: withField, source, steps })

  return (
    <TransformationsEditor
      field={ withField }
      onChange={ setSteps }
      preview={ preview }
      steps={ steps }
    />
  )
}

const renderEditor = (given: Partial<MappingRow> = {}, withField: MappingField = field): void => {
  render(<Harness
    given={ given }
    withField={ withField }
         />)
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
  fireEvent.click(addButton(label))
}

const addButton = (label: string): HTMLButtonElement => screen.getByRole<HTMLButtonElement>('button', { name: `+ ${label}` })

beforeEach(() => {
  jest.useFakeTimers()
  mockPreview.mockReset()
  mockPreview.mockResolvedValue({ data: answer() })
  mockTrackError.mockReset()
})

afterEach(() => {
  cleanup()
  jest.useRealTimers()
})

describe('the transformations of a column', () => {
  it('runs on the stored file under its settings, for the column and its field', async () => {
    renderEditor()
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
    expect(screen.getByText('Applied top to bottom to this column\'s value, before the automatic conversion for the field\'s type.')).toBeTruthy()
  })

  it('shows each step\'s value for the first row and the conversion of the field', async () => {
    mockPreview.mockResolvedValue({
      data: answer({
        steps: [{ type: 'trim', resultType: 'default', error: null, values: [cell('Chair'), cell(), cell('x')] }],
        results: [cell('Chair'), cell(), { items: [], failed: true }],
        conversion: 'numeric'
      })
    })
    renderEditor({ transformations: [{ type: 'trim', settings: { mode: 'both' } }] })
    await settle()

    expect(screen.getByText('→ Chair')).toBeTruthy()
    expect(screen.getByText('Converted to a number for the field.')).toBeTruthy()
  })

  it('offers only the transformations the server names, each with its settings', async () => {
    mockPreview.mockResolvedValue({ data: answer({ offered: ['trim', 'loadAsset'] }) })
    renderEditor()
    await settle()

    expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual(['+ Trim', '+ Load asset'])

    addStep('Load asset')
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadAsset', settings: { loadStrategy: 'id' } }])
    fireEvent.click(screen.getByRole('radio', { name: 'Full path' }))
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadAsset', settings: { loadStrategy: 'path' } }])
  })

  it('offers no transformation until the steps added last have their answer', async () => {
    mockPreview.mockResolvedValue({ data: answer({ offered: ['trim', 'loadAsset'] }) })
    renderEditor()
    await settle()

    addStep('Load asset')
    expect(addButton('Trim').disabled).toBe(true)

    await settle()
    expect(addButton('Trim').disabled).toBe(false)
  })

  it('runs the values again once typing pauses', async () => {
    renderEditor({ transformations: [{ type: 'stringReplace', settings: { search: '', replace: '' } }] })
    await settle()

    fireEvent.change(screen.getByLabelText('Find'), { target: { value: ',' } })
    fireEvent.change(screen.getByLabelText('Replace with'), { target: { value: '.' } })
    await settle()

    expect(mockPreview).toHaveBeenCalledTimes(2)
    expect(lastPipeline()).toEqual([{ type: 'stringReplace', settings: { search: ',', replace: '.' } }])
  })

  it('takes each transformation\'s settings', async () => {
    renderEditor({
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
    renderEditor({ transformations: [{ type: 'trim', settings: { mode: 'both' } }, { type: 'loadDataObject', settings: { loadStrategy: 'id' } }] })
    await settle()

    fireEvent.click(screen.getByRole('button', { name: 'Remove Trim' }))
    await settle()

    expect(lastPipeline()).toEqual([{ type: 'loadDataObject', settings: { loadStrategy: 'id' } }])
    expect(within(screen.getByRole('list', { name: 'Transformations' })).getAllByRole('listitem')).toHaveLength(1)
  })

  it('shows no settings for a type of step it has none for', () => {
    const { container } = render(
      <TransformationSettings
        id="transformation-0"
        onChange={ jest.fn() }
        step={ { type: 'unknownOperator', settings: { mode: 'x' } } }
      />
    )

    expect(container.textContent).toBe('')
    expect(container.querySelectorAll('input, select, button')).toHaveLength(0)
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
    renderEditor({ transformations: [{ type: 'loadAsset', settings: { loadStrategy: 'path' } }, { type: 'trim', settings: { mode: 'both' } }] })
    await settle()

    const alerts = screen.getAllByRole('alert').map((alert) => alert.textContent)
    expect(alerts).toEqual([
      'This transformation cannot take the values of the one before it.',
      'The values cannot go into Name <en> yet. Add a transformation that leads there, or choose another field.'
    ])
    expect(screen.queryByRole('button', { name: /^\+ / })).toBeNull()
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
    renderEditor({ transformations: [{ type: 'conditionalConversion', settings: { original: 'S|M|L', converted: 'small|medium' } }] })
    await settle()

    expect(screen.getAllByRole('alert')[0].textContent).toBe('Give as many values under Into as under Values.')
  })

  it('takes only the answer to the steps asked for last', async () => {
    let late: (value: unknown) => void = () => {}
    mockPreview.mockResolvedValue({ data: answer({ offered: ['trim'] }) })
    renderEditor()
    await settle()

    mockPreview.mockReturnValueOnce(new Promise((resolve) => { late = resolve }))
    addStep('Trim')
    await settle()
    fireEvent.change(screen.getByLabelText('Remove spaces'), { target: { value: 'left' } })
    await settle()

    await act(async () => { late({ data: answer({ offered: ['loadAsset'], fits: false }) }) })

    expect(mockPreview).toHaveBeenCalledTimes(3)
    expect(screen.getByRole('button', { name: '+ Trim' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: '+ Load asset' })).toBeNull()
    expect(lastPipeline()).toEqual([{ type: 'trim', settings: { mode: 'left' } }])
  })

  it('reports a failed request', async () => {
    mockPreview.mockResolvedValue({ error: { status: 422, data: { message: 'no' } } })
    renderEditor()
    await settle()

    expect(mockTrackError).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('button', { name: /^\+ / })).toBeNull()
  })
})

describe('what the transformations of a column run on', () => {
  const file = { name: 'products.csv', size: 10 }
  const inspected: InspectedFile = {
    maxRows: 5000,
    fileId: 'file-1',
    type: 'csv',
    delimiter: ';',
    enclosure: '"',
    escape: '\\',
    hasHeaderRow: true,
    sheets: [],
    rowCount: 1,
    columns: [],
    firstRows: []
  }

  it('is the stored file under its settings, for the class chosen', () => {
    expect(transformationSourceOf({ kind: 'inspected', file, inspected }, 'PROD')).toEqual({
      fileId: 'file-1',
      classId: 'PROD',
      settings: { delimiter: ';', enclosure: '"', escape: '\\', hasHeaderRow: true, sheetName: undefined }
    })
  })

  it('is nothing without a stored file or a class', () => {
    expect(transformationSourceOf({ kind: 'empty' }, 'PROD')).toBeNull()
    expect(transformationSourceOf({ kind: 'inspected', file, inspected: { ...inspected, fileId: null } }, 'PROD')).toBeNull()
    expect(transformationSourceOf({ kind: 'inspected', file, inspected }, undefined)).toBeNull()
  })
})
