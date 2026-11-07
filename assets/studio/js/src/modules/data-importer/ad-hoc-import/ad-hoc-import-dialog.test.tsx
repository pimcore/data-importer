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
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { AdHocImportDialog } from './ad-hoc-import-dialog'
import { type InspectedFile } from './file-step/file-step-state'
import { UploadAborted, UploadFailed } from './file-step/upload-file'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ formatDataUnit: jest.requireActual<{ formatDataUnit: unknown }>('../../../../test/support/ad-hoc-import').formatDataUnit }))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: class { constructor (readonly details: unknown) {} },
  GeneralError: class { constructor (readonly message: string) {} },
  trackError: (...args: unknown[]) => mockTrackError(...args)
}))
jest.mock('@pimcore/studio-ui-bundle/api/class-definition', () => ({
  api: { endpoints: { classDefinitionCollectionCreatable: { useQuery: () => mockClasses() } } }
}))
// picks a file at once: what the real one does with a dropped file has a test of its own
jest.mock('./file-step/file-drop-zone', () => ({
  FileDropZone: ({ onFile }: { onFile: (file: File) => void }) => (
    <input
      aria-label="drop zone"
      onChange={ (event) => { onFile(event.target.files![0]) } }
      type="file"
    />
  )
}))
jest.mock('./file-step/upload-file', () => ({
  ...jest.requireActual<object>('./file-step/upload-file'),
  uploadFile: (...args: unknown[]) => mockUploadFile(...args)
}))
jest.mock('../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportInspectFileMutation: () => [mockInspectFile],
  useBundleDataImporterAdHocImportDeleteFileMutation: () => [mockDeleteFile],
  useBundleDataImporterAdHocImportLoadMatchFieldsQuery: (...args: unknown[]) => mockLoadMatchFields(...args),
  useBundleDataImporterAdHocImportLoadMappingMutation: () => [mockLoadMapping]
}))

const mockTrackError = jest.fn()
const mockClasses = jest.fn()
const mockUploadFile = jest.fn()
const mockInspectFile = jest.fn()
const mockDeleteFile = jest.fn()
const mockLoadMatchFields = jest.fn()
const mockLoadMapping = jest.fn()

const csv: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  delimiter: ';',
  detectedDelimiter: ';',
  enclosure: '"',
  escape: '\\',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 312,
  columns: [{ dataIndex: 'Artikelnr', label: 'Artikelnr' }, { dataIndex: 'Bezeichnung', label: 'Bezeichnung' }],
  firstRows: [['NH-1001', 'Trail jacket'], ['NH-1002', null]]
}

const xlsx: InspectedFile = {
  ...csv,
  type: 'xlsx',
  delimiter: null,
  detectedDelimiter: null,
  enclosure: null,
  escape: null,
  sheetName: 'Products',
  sheets: ['Products', 'Prices'],
  rowCount: 312,
  columns: Array.from({ length: 14 }, (_, position) => ({ dataIndex: String(position), label: `Column ${position}` })),
  firstRows: []
}

const refused = (given: Partial<InspectedFile>): InspectedFile => ({
  ...csv,
  fileId: null,
  delimiter: null,
  detectedDelimiter: null,
  rowCount: 0,
  columns: [],
  firstRows: [],
  ...given
})

interface Running {
  resolve: (inspected: InspectedFile) => void
  reject: (error: unknown) => void
  abort: jest.Mock
  progress: (loaded: number, total: number) => void
}

const onClose = jest.fn()

const renderDialog = (folderPath = '/Products/Outdoor'): void => {
  render(
    <AdHocImportDialog
      onClose={ onClose }
      target={ { folderId: 77, folderPath, folderLabel: 'Home' } }
    />
  )
}

const dropFile = (name: string, size = 84000): Running => {
  const file = new File(['x'], name)
  Object.defineProperty(file, 'size', { value: size })

  let running: Running | undefined
  mockUploadFile.mockImplementationOnce((_: File, __: number, progress: Running['progress']) => {
    const abort = jest.fn()
    const result = new Promise<InspectedFile>((resolve, reject) => {
      running = { resolve, reject, abort, progress }
      abort.mockImplementation(() => { reject(new UploadAborted()) })
    })

    return { result, abort }
  })

  fireEvent.change(screen.getByLabelText('drop zone'), { target: { files: [file] } })

  return running!
}

const dropAndRead = async (inspected: InspectedFile, name = 'prices.csv', size = 21000): Promise<void> => {
  const running = dropFile(name, size)
  await act(async () => { running.resolve(inspected) })
}

const mappingAnswer = {
  fields: [
    { key: 'sku', title: 'SKU', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true },
    { key: 'title', title: 'Title', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true },
    { key: 'image', title: 'Image', localized: false, mandatory: false, transformationResultType: 'asset', takesColumnAsIs: false }
  ],
  suggestions: [{
    sourceIndex: 'Bezeichnung',
    sourceLabel: 'Bezeichnung',
    targetFieldName: 'title',
    targetFieldLabel: 'Title',
    transformationResultType: 'default',
    score: 100,
    language: null
  }]
}

const select = (label: string, value: string): void => {
  fireEvent.change(screen.getByLabelText(label), { target: { value } })
}

const nextButton = (): HTMLElement => screen.getByRole('button', { name: 'Next' })

const chooseWhatToMatchBy = (): void => {
  select('Field', 'sku')
  select('from column', 'Artikelnr')
}

beforeEach(() => {
  mockClasses.mockReturnValue({ data: { items: [{ id: 'PROD', name: 'Product' }] }, isLoading: false })
  mockLoadMatchFields.mockReturnValue({
    currentData: { attributes: [{ key: 'sku', title: 'SKU' }, { key: 'name', title: 'Name', localized: true }] },
    isFetching: false
  })
  mockDeleteFile.mockResolvedValue({ data: undefined })
  mockLoadMapping.mockResolvedValue({ data: mappingAnswer })
})

afterEach(() => {
  cleanup()
  jest.resetAllMocks()
})

describe('the file step without a file', () => {
  it('names the folder and shows the first step, the drop zone and the objects panel', () => {
    renderDialog()

    expect(screen.getByRole('dialog', { name: 'Import file into Products / Outdoor' })).toBeTruthy()
    expect(screen.getByText('File', { selector: 'li' }).getAttribute('aria-current')).toBe('step')
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
    expect(screen.getByRole('region', { name: 'Objects' })).toBeTruthy()
    expect(screen.getByText('Found objects are updated where they are.')).toBeTruthy()
    expect(screen.getByText('Empty: the folder you clicked.')).toBeTruthy()
  })

  it('names the root folder by its label, as it has no path', () => {
    renderDialog('/')

    expect(screen.getByRole('dialog', { name: 'Import file into Home' })).toBeTruthy()
  })

  it('has no column to choose yet and cannot go on', () => {
    renderDialog()

    const column = screen.getByLabelText<HTMLSelectElement>('from column')
    expect(column.disabled).toBe(true)
    expect(within(column).getByText('after the file')).toBeTruthy()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('takes the only class there is', () => {
    renderDialog()

    expect(screen.getByLabelText<HTMLSelectElement>('Class').value).toBe('PROD')
  })

  it('leaves the choice among several classes', () => {
    mockClasses.mockReturnValue({ data: { items: [{ id: 'PROD', name: 'Product' }, { id: 'CAT', name: 'Category' }] }, isLoading: false })

    renderDialog()

    expect(screen.getByLabelText<HTMLSelectElement>('Class').value).toBe('')
  })

  it('offers the fields of the chosen class, without localized ones', () => {
    renderDialog()

    expect(mockLoadMatchFields).toHaveBeenLastCalledWith({ classId: 'PROD' }, { skip: false })
    const field = screen.getByLabelText<HTMLSelectElement>('Field')
    expect(within(field).getByText('SKU')).toBeTruthy()
    expect(within(field).queryByText('Name')).toBeNull()
  })

  it('loads no fields before a class is chosen', () => {
    mockClasses.mockReturnValue({ data: { items: [] }, isLoading: false })

    renderDialog()

    expect(mockLoadMatchFields).toHaveBeenLastCalledWith({ classId: '' }, { skip: true })
  })

  it('forgets the field when another class is chosen', () => {
    mockClasses.mockReturnValue({ data: { items: [{ id: 'PROD', name: 'Product' }, { id: 'CAT', name: 'Category' }] }, isLoading: false })
    renderDialog()
    select('Class', 'PROD')
    select('Field', 'sku')

    select('Class', 'CAT')

    expect(screen.getByLabelText<HTMLSelectElement>('Field').value).toBe('')
  })
})

describe('while the fields of another class load', () => {
  it('offers none of the previous class, so none of them can be chosen', () => {
    mockLoadMatchFields.mockReturnValue({
      data: { attributes: [{ key: 'sku', title: 'SKU' }] },
      currentData: undefined,
      isFetching: true
    })

    renderDialog()

    expect(within(screen.getByLabelText('Field')).queryByText('SKU')).toBeNull()
  })
})

describe('uploading', () => {
  it('posts to the folder and shows the progress, without the objects panel', () => {
    renderDialog()

    const running = dropFile('northhike-autumn.xlsx')
    act(() => { running.progress(54000, 84000) })

    expect(mockUploadFile.mock.calls[0][1]).toBe(77)
    expect(screen.getByText('northhike-autumn.xlsx')).toBeTruthy()
    expect(screen.getByText('XLSX')).toBeTruthy()
    expect(screen.getByText('64 % · 54 of 84 KB')).toBeTruthy()
    expect(screen.getByRole<HTMLProgressElement>('progressbar').value).toBe(64)
    expect(screen.queryByRole('region', { name: 'Objects' })).toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('can be cancelled, which leaves nothing to delete', async () => {
    renderDialog()
    const running = dropFile('prices.csv')

    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'Cancel upload' })) })

    expect(running.abort).toHaveBeenCalled()
    expect(mockDeleteFile).not.toHaveBeenCalled()
    expect(mockTrackError).not.toHaveBeenCalled()
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
  })

  it('is aborted when the dialog closes', async () => {
    renderDialog()
    const running = dropFile('prices.csv')

    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'Cancel' })) })

    expect(running.abort).toHaveBeenCalled()
    expect(onClose).toHaveBeenCalled()
  })

  it('is aborted when the dialog goes away', async () => {
    renderDialog()
    const running = dropFile('prices.csv')

    await act(async () => { cleanup() })

    expect(running.abort).toHaveBeenCalled()
  })

  it('ignores an answer that arrives after it was cancelled', async () => {
    renderDialog()
    const running = dropFile('prices.csv')
    running.abort.mockImplementation(() => undefined)

    fireEvent.click(screen.getByRole('button', { name: 'Cancel upload' }))
    await act(async () => { running.resolve(csv) })

    expect(screen.getByLabelText('drop zone')).toBeTruthy()
    expect(screen.queryByText('prices.csv')).toBeNull()
  })

  it('reports a refusal of the server and starts over', async () => {
    renderDialog()
    const running = dropFile('prices.csv')

    await act(async () => { running.reject(new UploadFailed({ message: 'Access denied' })) })

    expect(mockTrackError.mock.calls[0][0].details).toEqual({ status: 500, data: { message: 'Access denied' } })
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
  })

  it('reports a broken connection and starts over', async () => {
    renderDialog()
    const running = dropFile('prices.csv')

    await act(async () => { running.reject(new UploadFailed(null)) })

    expect(mockTrackError.mock.calls[0][0].message).toBe('The file could not be uploaded')
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
  })
})

describe('a CSV the server read', () => {
  it('shows its numbers, its settings and its first rows', async () => {
    renderDialog()

    await dropAndRead(csv)

    expect(screen.getByText('312 rows · 2 columns · 21 KB')).toBeTruthy()
    expect(within(screen.getByLabelText('Delimiter')).getByText('; Semicolon (detected)')).toBeTruthy()
    expect(within(screen.getByLabelText('Delimiter')).getByText(', Comma')).toBeTruthy()
    expect(screen.getByLabelText<HTMLSelectElement>('Delimiter').value).toBe(';')
    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe('"')
    expect(screen.getByLabelText<HTMLInputElement>('Escape').value).toBe('\\')
    expect(screen.getByLabelText<HTMLInputElement>('in the first row').checked).toBe(true)
    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Artikelnr', 'Bezeichnung'])
    expect(screen.getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['NH-1001', 'Trail jacket', 'NH-1002', ''])
  })

  it('goes on once the class and what to match by are chosen', async () => {
    renderDialog()
    await dropAndRead(csv)
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)

    chooseWhatToMatchBy()

    expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
  })

  it('matches by the object id with a column and no field', async () => {
    renderDialog()
    await dropAndRead(csv)

    fireEvent.click(screen.getByRole('radio', { name: 'Object ID' }))
    expect(screen.queryByLabelText('Field')).toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)

    select('from column', 'Artikelnr')

    expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
  })

  it('cannot go on without rows', async () => {
    renderDialog()
    await dropAndRead({ ...csv, rowCount: 0, firstRows: [] })

    chooseWhatToMatchBy()

    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('moves on to the mapping and back, keeping what was chosen', async () => {
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()

    await act(async () => { fireEvent.click(nextButton()) })

    expect(screen.getByText('Mapping', { selector: 'li' }).getAttribute('aria-current')).toBe('step')
    expect(screen.queryByRole('region', { name: 'File' })).toBeNull()

    fireEvent.click(screen.getByRole('button', { name: 'Back' }))

    expect(screen.getByText('File', { selector: 'li' }).getAttribute('aria-current')).toBe('step')
    expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('Artikelnr')
    expect(screen.getByText('312 rows · 2 columns · 21 KB')).toBeTruthy()
    expect(mockDeleteFile).not.toHaveBeenCalled()
  })

  it('keeps the chosen folder for new objects', async () => {
    renderDialog()
    await dropAndRead(csv)

    fireEvent.change(screen.getByLabelText('folder'), { target: { value: '/Products/New' } })

    expect(screen.getByLabelText<HTMLInputElement>('folder').value).toBe('/Products/New')
  })
})

describe('changing the settings', () => {
  it('reads the file again with them', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...csv, delimiter: ',' } })
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { select('Delimiter', ',') })

    expect(mockInspectFile).toHaveBeenCalledWith({
      fileId: 'file-1',
      bundleDataImporterAdHocImportFileSettings: { delimiter: ',', enclosure: '"', escape: '\\', hasHeaderRow: true, sheetName: undefined }
    })
    expect(screen.getByLabelText<HTMLSelectElement>('Delimiter').value).toBe(',')
  })

  it('reads again for an enclosure, an empty escape and the first row', async () => {
    mockInspectFile.mockResolvedValue({ data: csv })
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: "'" } }) })
    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.enclosure).toBe("'")

    await act(async () => { fireEvent.change(screen.getByLabelText('Escape'), { target: { value: '' } }) })
    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.escape).toBe('')

    await act(async () => { fireEvent.click(screen.getByLabelText('in the first row')) })
    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.hasHeaderRow).toBe(false)
  })

  it('does not read again while the enclosure is empty', async () => {
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: '' } }) })

    expect(mockInspectFile).not.toHaveBeenCalled()
    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe('')
  })

  it('shows the enclosure again when it is left empty', async () => {
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: '' } }) })
    await act(async () => { fireEvent.blur(screen.getByLabelText('Enclosure')) })

    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe('"')
    expect(mockInspectFile).not.toHaveBeenCalled()
  })

  it('keeps a change another one follows before the answer came, and shows what the server read', async () => {
    const answers: Array<(value: unknown) => void> = []
    mockInspectFile.mockImplementation(async () => await new Promise((resolve) => { answers.push(resolve) }))
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: "'" } }) })
    await act(async () => { fireEvent.click(screen.getByLabelText('in the first row')) })

    const settings = mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings
    expect(settings.enclosure).toBe("'")
    expect(settings.hasHeaderRow).toBe(false)

    await act(async () => { answers[0]({ data: csv }) })
    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe("'")

    await act(async () => { answers[1]({ data: { ...csv, enclosure: '~', hasHeaderRow: false } }) })
    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe('~')
  })

  it('builds the next change on the answer, once it came', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...csv, enclosure: '~' } })
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: "'" } }) })
    await act(async () => { select('Delimiter', ',') })

    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.enclosure).toBe('~')
  })

  it('forgets a pending change with the file it was for', async () => {
    mockInspectFile.mockReturnValueOnce(new Promise(() => undefined))
    renderDialog()
    await dropAndRead(csv)
    await act(async () => { fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: "'" } }) })

    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'Remove file' })) })
    await dropAndRead(csv)

    mockInspectFile.mockResolvedValue({ data: csv })
    await act(async () => { select('Delimiter', ',') })
    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.enclosure).toBe('"')
  })

  it('cannot go on while the file is being read', async () => {
    let answer: (value: unknown) => void = () => undefined
    mockInspectFile.mockReturnValue(new Promise((resolve) => { answer = resolve }))
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()

    await act(async () => { select('Delimiter', ',') })

    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
    expect(screen.getByRole('table').getAttribute('aria-busy')).toBe('true')

    await act(async () => { answer({ data: csv }) })

    expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
  })

  it('takes only the answer to the latest settings', async () => {
    const answers: Array<(value: unknown) => void> = []
    mockInspectFile.mockImplementation(async () => await new Promise((resolve) => { answers.push(resolve) }))
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { select('Delimiter', ',') })
    await act(async () => { select('Delimiter', '|') })

    await act(async () => { answers[1]({ data: { ...csv, delimiter: '|' } }) })
    await act(async () => { answers[0]({ data: { ...csv, delimiter: ',' } }) })

    expect(screen.getByLabelText<HTMLSelectElement>('Delimiter').value).toBe('|')
  })

  it('forgets a matched column the file no longer has', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...csv, hasHeaderRow: false, columns: [{ dataIndex: '0', label: '0' }], firstRows: [] } })
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()

    await act(async () => { fireEvent.click(screen.getByLabelText('in the first row')) })

    expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('')
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('keeps the settings of a file they make unreadable, so they can be changed back', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...csv, refusal: 'unreadable', columns: [], firstRows: [] } })
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { select('Delimiter', ',') })

    expect(within(screen.getByRole('alert')).getByText('This file cannot be read as CSV.')).toBeTruthy()
    expect(screen.getByLabelText('Delimiter')).toBeTruthy()
    expect(screen.queryByRole('table')).toBeNull()
    expect(screen.queryByRole('region', { name: 'Objects' })).toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('ignores an answer that arrives after the file was removed', async () => {
    let answer: (value: unknown) => void = () => undefined
    mockInspectFile.mockReturnValue(new Promise((resolve) => { answer = resolve }))
    renderDialog()
    await dropAndRead(csv)
    await act(async () => { select('Delimiter', ',') })

    fireEvent.click(screen.getByRole('button', { name: 'Remove file' }))
    await act(async () => { answer({ data: csv }) })

    expect(screen.getByLabelText('drop zone')).toBeTruthy()
  })

  it('starts over when the file is gone', async () => {
    mockInspectFile.mockResolvedValue({ error: { status: 404, data: {} } })
    renderDialog()
    await dropAndRead(csv)

    await act(async () => { select('Delimiter', ',') })

    expect(mockTrackError).toHaveBeenCalled()
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
  })
})

describe('a read that fails while the file may still be there', () => {
  const failingRead = async (): Promise<void> => {
    renderDialog()
    await dropAndRead(csv)
    mockInspectFile.mockResolvedValue({ error: { status: 500, data: {} } })
  }

  it('keeps the file, its settings and its first rows, and reports the error', async () => {
    await failingRead()

    select('Delimiter', ',')
    await act(async () => {})

    expect(mockTrackError).toHaveBeenCalledTimes(1)
    expect(screen.getByText('prices.csv')).toBeTruthy()
    expect(screen.getByLabelText<HTMLSelectElement>('Delimiter').value).toBe(';')
    expect(screen.getByText('NH-1001')).toBeTruthy()
    expect(screen.queryByLabelText('drop zone')).toBeNull()
  })

  it('keeps it after a broken connection as well', async () => {
    renderDialog()
    await dropAndRead(csv)
    mockInspectFile.mockResolvedValue({ error: { status: 'FETCH_ERROR', error: 'offline' } })

    select('Delimiter', ',')
    await act(async () => {})

    expect(screen.getByText('prices.csv')).toBeTruthy()
    expect(screen.queryByLabelText('drop zone')).toBeNull()
  })

  it('brings a typed character back to the one last read', async () => {
    await failingRead()

    fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: '~' } })
    await act(async () => {})

    expect(screen.getByLabelText<HTMLInputElement>('Enclosure').value).toBe('"')
  })

  it('reads again from the settings last read, and deletes the file on leaving', async () => {
    await failingRead()
    select('Delimiter', ',')
    await act(async () => {})

    mockInspectFile.mockResolvedValue({ data: csv })
    fireEvent.change(screen.getByLabelText('Enclosure'), { target: { value: '~' } })
    await act(async () => {})

    const settings = mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings
    expect(settings.delimiter).toBe(';')
    expect(settings.enclosure).toBe('~')

    fireEvent.click(screen.getByRole('button', { name: 'close dialog' }))

    expect(mockDeleteFile).toHaveBeenCalledWith({ fileId: 'file-1' })
  })
})

describe('an XLSX the server read', () => {
  it('offers its sheets and says how many columns the first rows leave out', async () => {
    renderDialog()

    await dropAndRead(xlsx, 'northhike-autumn.xlsx', 84000)

    expect(screen.getByText('312 rows · 14 columns · 84 KB')).toBeTruthy()
    expect(screen.queryByLabelText('Delimiter')).toBeNull()
    expect(screen.getByLabelText<HTMLSelectElement>('Sheet').value).toBe('Products')
    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual([
      'Column 0', 'Column 1', 'Column 2', 'Column 3', 'Column 4', 'Column 5', '+8 more'
    ])
  })

  it('shows the values of the first rows up to the left-out columns, whose own cell stays empty', async () => {
    const values = Array.from({ length: 14 }, (_, position) => `value ${position}`)
    renderDialog()

    await dropAndRead({ ...xlsx, firstRows: [values] }, 'northhike-autumn.xlsx', 84000)

    expect(within(screen.getAllByRole('row')[1]).getAllByRole('cell').map((cell) => cell.textContent)).toEqual([
      'value 0', 'value 1', 'value 2', 'value 3', 'value 4', 'value 5', ''
    ])
  })

  it('reads the chosen sheet', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...xlsx, sheetName: 'Prices' } })
    renderDialog()
    await dropAndRead(xlsx, 'northhike-autumn.xlsx')

    await act(async () => { select('Sheet', 'Prices') })

    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.sheetName).toBe('Prices')
    expect(screen.getByLabelText<HTMLSelectElement>('Sheet').value).toBe('Prices')
  })

  it('reads the first row with its switch', async () => {
    mockInspectFile.mockResolvedValue({ data: { ...xlsx, hasHeaderRow: false } })
    renderDialog()
    await dropAndRead(xlsx, 'northhike-autumn.xlsx')

    await act(async () => { fireEvent.click(screen.getByLabelText('in the first row')) })

    expect(mockInspectFile.mock.lastCall[0].bundleDataImporterAdHocImportFileSettings.hasHeaderRow).toBe(false)
  })

  describe('with a column chosen to match by', () => {
    const chooseFirstColumn = async (first: InspectedFile): Promise<void> => {
      renderDialog()
      await dropAndRead(first, 'northhike-autumn.xlsx')
      select('Field', 'sku')
      select('from column', '0')
    }

    it('forgets it when the chosen sheet has another column at its position', async () => {
      mockInspectFile.mockResolvedValue({ data: { ...xlsx, sheetName: 'Prices', columns: [{ dataIndex: '0', label: 'price' }] } })
      await chooseFirstColumn({ ...xlsx, columns: [{ dataIndex: '0', label: 'sku' }] })

      await act(async () => { select('Sheet', 'Prices') })

      expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('')
      expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
    })

    it('keeps it when the chosen sheet has the same column at its position', async () => {
      mockInspectFile.mockResolvedValue({ data: { ...xlsx, sheetName: 'Prices', columns: [{ dataIndex: '0', label: 'sku' }] } })
      await chooseFirstColumn({ ...xlsx, columns: [{ dataIndex: '0', label: 'sku' }] })

      await act(async () => { select('Sheet', 'Prices') })

      expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('0')
      expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
    })

    it('forgets it on another sheet when the columns have no names', async () => {
      const columns = [{ dataIndex: '0', label: '0' }]
      mockInspectFile.mockResolvedValue({ data: { ...xlsx, hasHeaderRow: false, sheetName: 'Prices', columns } })
      await chooseFirstColumn({ ...xlsx, hasHeaderRow: false, columns })

      await act(async () => { select('Sheet', 'Prices') })

      expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('')
    })

    it('does not offer it for the next file after the file was removed', async () => {
      await chooseFirstColumn(xlsx)

      fireEvent.click(screen.getByRole('button', { name: 'Remove file' }))
      await dropAndRead(xlsx, 'another.xlsx')

      expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('')
      expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
    })

    it('does not offer it for the next file after the file was gone', async () => {
      mockInspectFile.mockResolvedValue({ error: { status: 404, data: {} } })
      await chooseFirstColumn(xlsx)

      await act(async () => { select('Sheet', 'Prices') })
      await dropAndRead(xlsx, 'another.xlsx')

      expect(screen.getByLabelText<HTMLSelectElement>('from column').value).toBe('')
    })
  })

  it('keeps the sheets of a workbook whose first sheet is over the limit', async () => {
    renderDialog()

    await dropAndRead({ ...xlsx, refusal: 'too-many-rows', rowCount: 8412, columns: [] }, 'catalogue.xlsx')

    expect(within(screen.getByRole('alert')).getByText('This file has 8,412 rows; an ad hoc import takes up to 5,000.')).toBeTruthy()
    expect(screen.getByLabelText('Sheet')).toBeTruthy()
  })
})

describe('a refused file', () => {
  it('over the limit says so with its numbers and offers nothing else', async () => {
    renderDialog()

    await dropAndRead(refused({ type: 'xlsx', refusal: 'too-many-rows', rowCount: 8412 }), 'northhike-full-catalogue.xlsx', 2100000)

    expect(screen.getByText('8,412 rows · 2.1 MB')).toBeTruthy()
    const alert = within(screen.getByRole('alert'))
    expect(alert.getByText('This file has 8,412 rows; an ad hoc import takes up to 5,000.')).toBeTruthy()
    expect(alert.getByText('Split the file, or import it with a Data Importer configuration in the Data Hub.')).toBeTruthy()
    expect(screen.queryByLabelText('Sheet')).toBeNull()
    expect(screen.queryByRole('table')).toBeNull()
    expect(screen.queryByRole('region', { name: 'Objects' })).toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })

  it('that reaches too far says so and points to a configuration', async () => {
    renderDialog()
    await dropAndRead(refused({ type: 'xlsx', refusal: 'too-many-cells' }), 'sparse.xlsx')

    expect(screen.getByRole('alert').textContent).toContain('This sheet reaches too far for an ad hoc import.')
    expect(screen.getByRole('alert').textContent).toContain('Split the file, or import it with a Data Importer configuration in the Data Hub.')
  })

  it('that cannot be read names its type', async () => {
    renderDialog()

    await dropAndRead(refused({ type: 'xlsx', refusal: 'unreadable' }), 'broken.xlsx', 2100000)

    expect(screen.getByText('2.1 MB')).toBeTruthy()
    expect(within(screen.getByRole('alert')).getByText('This file cannot be read as XLSX.')).toBeTruthy()
  })

  it('of another format the server found says which ones are taken', async () => {
    renderDialog()

    await dropAndRead(refused({ type: null, refusal: 'wrong-format' }))

    expect(within(screen.getByRole('alert')).getByText('Only XLSX and CSV files can be imported.')).toBeTruthy()
  })

  it('of another format is refused without uploading it', () => {
    renderDialog()

    fireEvent.change(screen.getByLabelText('drop zone'), { target: { files: [new File(['x'], 'catalogue.pdf')] } })

    expect(mockUploadFile).not.toHaveBeenCalled()
    expect(within(screen.getByRole('alert')).getByText('Only XLSX and CSV files can be imported.')).toBeTruthy()
    expect(screen.getByText('PDF')).toBeTruthy()
  })

  it('can be removed without a delete, as nothing was kept, to try another', async () => {
    renderDialog()
    await dropAndRead(refused({ type: 'xlsx', refusal: 'too-many-rows', rowCount: 8412 }), 'big.xlsx')

    fireEvent.click(screen.getByRole('button', { name: 'Remove file' }))

    expect(mockDeleteFile).not.toHaveBeenCalled()
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
    expect(screen.getByRole('region', { name: 'Objects' })).toBeTruthy()
  })
})

describe('leaving', () => {
  it('removing the file deletes it and starts over', async () => {
    renderDialog()
    await dropAndRead(csv)

    fireEvent.click(screen.getByRole('button', { name: 'Remove file' }))

    expect(mockDeleteFile).toHaveBeenCalledWith({ fileId: 'file-1' })
    expect(screen.getByLabelText('drop zone')).toBeTruthy()
    expect(onClose).not.toHaveBeenCalled()
  })

  it.each([['Cancel'], ['close dialog']])('%s deletes the file and closes', async (name) => {
    renderDialog()
    await dropAndRead(csv)

    fireEvent.click(screen.getByRole('button', { name }))

    expect(mockDeleteFile).toHaveBeenCalledWith({ fileId: 'file-1' })
    expect(onClose).toHaveBeenCalled()
  })

  it('reports a file that could not be deleted', async () => {
    mockDeleteFile.mockResolvedValue({ error: { status: 500, data: {} } })
    renderDialog()
    await dropAndRead(csv)

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await waitFor(() => { expect(mockTrackError).toHaveBeenCalled() })
  })

  it('closing without a file deletes nothing', () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(mockDeleteFile).not.toHaveBeenCalled()
    expect(onClose).toHaveBeenCalled()
  })
})

describe('the mapping step', () => {
  const toMapping = async (): Promise<void> => {
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()
    await act(async () => { fireEvent.click(nextButton()) })
  }

  it('asks for the mapping of the class with the columns of the file and shows a row per column', async () => {
    await toMapping()

    expect(mockLoadMapping).toHaveBeenCalledWith({
      bundleDataImporterMappingSuggestionParameters: {
        classId: 'PROD',
        columns: [{ index: 'Artikelnr', label: 'Artikelnr' }, { index: 'Bezeichnung', label: 'Bezeichnung' }]
      }
    })
    expect(within(screen.getByRole('list', { name: 'Columns of the file' })).getAllByRole('listitem')).toHaveLength(2)
    expect(screen.getByLabelText<HTMLSelectElement>('Field for Bezeichnung').value).toBe('title')
    expect(screen.getByText('auto')).toBeTruthy()
  })

  it('shows that it loads until the answer comes', async () => {
    let answer: (value: unknown) => void = () => {}
    mockLoadMapping.mockReturnValue(new Promise((resolve) => { answer = resolve }))
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()
    fireEvent.click(nextButton())

    expect(document.querySelector('[aria-busy="true"]')).not.toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)

    await act(async () => { answer({ data: mappingAnswer }) })

    expect(document.querySelector('[aria-busy="true"]')).toBeNull()
  })

  it('goes on to the preview once a column is imported', async () => {
    await toMapping()

    expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
    select('Field for Bezeichnung', '-')
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)

    fireEvent.click(screen.getByRole('button', { name: 'Restore Bezeichnung' }))
    select('Field for Bezeichnung', 'title')
    fireEvent.click(nextButton())

    expect(screen.getByText('Preview', { selector: 'li' }).getAttribute('aria-current')).toBe('step')
  })

  it('waits on a column whose values do not fit its field, and offers its transformations', async () => {
    await toMapping()

    select('Field for Bezeichnung', 'image')

    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
    expect(screen.getByText('Columns whose values do not fit their field yet: Bezeichnung')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Transformations of Bezeichnung' })).toBeTruthy()

    select('Field for Bezeichnung', 'title')
    expect((nextButton() as HTMLButtonElement).disabled).toBe(false)
  })

  it('keeps the mapping when going back and on with nothing changed', async () => {
    await toMapping()
    select('Field for Bezeichnung', '-')

    fireEvent.click(screen.getByRole('button', { name: 'Back' }))
    await act(async () => { fireEvent.click(nextButton()) })

    expect(mockLoadMapping).toHaveBeenCalledTimes(1)
    expect(screen.getByRole('region', { name: 'Not imported (1)' })).toBeTruthy()
  })

  it('makes a new mapping for another column to match by', async () => {
    await toMapping()
    select('Field for Bezeichnung', '-')

    fireEvent.click(screen.getByRole('button', { name: 'Back' }))
    select('from column', 'Bezeichnung')
    await act(async () => { fireEvent.click(nextButton()) })

    expect(mockLoadMapping).toHaveBeenCalledTimes(2)
    expect(screen.queryByRole('region', { name: 'Not imported (1)' })).toBeNull()
    expect(screen.getByText('Finds the objects')).toBeTruthy()
  })

  describe('of a workbook read again from another sheet', () => {
    const sku = { dataIndex: '0', label: 'sku' }
    const name = { dataIndex: '1', label: 'name' }
    const toSheetMapping = async (first: InspectedFile): Promise<void> => {
      renderDialog()
      await dropAndRead(first, 'northhike-autumn.xlsx')
      select('Field', 'sku')
      select('from column', '0')
      await act(async () => { fireEvent.click(nextButton()) })
      select('Field for name', 'title')
      fireEvent.click(screen.getByRole('button', { name: 'Back' }))
    }

    it('keeps the choices for the same column names and shows the values of that sheet', async () => {
      const products = { ...xlsx, columns: [sku, name], firstRows: [['NH-1', 'Trail jacket']] }
      mockInspectFile.mockResolvedValue({ data: { ...products, sheetName: 'Prices', firstRows: [['NH-1', 'Rain coat']] } })
      await toSheetMapping(products)

      await act(async () => { select('Sheet', 'Prices') })
      await act(async () => { fireEvent.click(nextButton()) })

      expect(mockLoadMapping).toHaveBeenCalledTimes(1)
      expect(screen.getByLabelText<HTMLSelectElement>('Field for name').value).toBe('title')
      expect(screen.getByText('Rain coat')).toBeTruthy()
      expect(screen.queryByText('Trail jacket')).toBeNull()
    })

    it('makes a new mapping when the columns have no names, even for the same match column', async () => {
      const headerless = { ...xlsx, hasHeaderRow: false, columns: [{ dataIndex: '0', label: '0' }, { dataIndex: '1', label: '1' }] }
      mockInspectFile.mockResolvedValue({ data: { ...headerless, sheetName: 'Prices' } })
      renderDialog()
      await dropAndRead(headerless, 'northhike-autumn.xlsx')
      select('Field', 'sku')
      select('from column', '0')
      await act(async () => { fireEvent.click(nextButton()) })
      select('Field for 1', 'title')
      fireEvent.click(screen.getByRole('button', { name: 'Back' }))

      await act(async () => { select('Sheet', 'Prices') })
      select('from column', '0')
      await act(async () => { fireEvent.click(nextButton()) })

      expect(mockLoadMapping).toHaveBeenCalledTimes(2)
      expect(screen.getByLabelText<HTMLSelectElement>('Field for 1').value).toBe('')
    })
  })

  it('takes only the answer to the mapping asked for last', async () => {
    const answers: Array<(value: unknown) => void> = []
    mockLoadMapping.mockImplementation(async () => await new Promise((resolve) => { answers.push(resolve) }))
    renderDialog()
    await dropAndRead(csv)
    chooseWhatToMatchBy()
    fireEvent.click(nextButton())
    fireEvent.click(screen.getByRole('button', { name: 'Back' }))
    select('from column', 'Bezeichnung')
    fireEvent.click(nextButton())

    await act(async () => { answers[1]({ data: { fields: mappingAnswer.fields, suggestions: [] } }) })
    await act(async () => { answers[0]({ data: mappingAnswer }) })

    expect(screen.queryByText('auto')).toBeNull()
    expect(screen.getByLabelText<HTMLSelectElement>('Field for Artikelnr').value).toBe('')
  })

  it('reports a mapping that could not be loaded and cannot go on', async () => {
    mockLoadMapping.mockResolvedValue({ error: { status: 403, data: {} } })
    await toMapping()

    expect(mockTrackError).toHaveBeenCalled()
    expect(screen.queryByRole('list', { name: 'Columns of the file' })).toBeNull()
    expect((nextButton() as HTMLButtonElement).disabled).toBe(true)
  })
})
