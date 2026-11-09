/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import {
  canContinue,
  fileBadge,
  hasAcceptedExtension,
  initialObjects,
  keepMatchColumn,
  refusedForFormat,
  type FilePhase,
  type InspectedFile,
  type ObjectsSettings
} from './file-step-state'

const file = { name: 'prices.csv', size: 10 }

const inspected = (given: Partial<InspectedFile> = {}): InspectedFile => ({
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 2,
  columns: [{ dataIndex: 'sku', label: 'sku' }],
  firstRows: [],
  ...given
})

const phase = (given: Partial<InspectedFile> = {}): FilePhase => ({ kind: 'inspected', file, inspected: inspected(given) })

const objects: ObjectsSettings = { ...initialObjects, classId: 'Product', matchField: 'sku', matchColumn: 'sku' }

describe('canContinue', () => {
  it('holds for an accepted file with rows, a class and what to match by', () => {
    expect(canContinue(phase(), objects)).toBe(true)
  })

  it('needs no field to match by the object id', () => {
    expect(canContinue(phase(), { ...objects, matchBy: 'id', matchField: undefined })).toBe(true)
  })

  it('needs no field, only the column naming the new objects, when not matching', () => {
    expect(canContinue(phase(), { ...objects, matchBy: 'none', matchField: undefined })).toBe(true)
    expect(canContinue(phase(), { ...objects, matchBy: 'none', matchColumn: undefined })).toBe(false)
  })

  it.each<[string, FilePhase, ObjectsSettings]>([
    ['no file', { kind: 'empty' }, objects],
    ['an upload still running', { kind: 'uploading', file, loaded: 1, total: 2 }, objects],
    ['a file not kept', phase({ fileId: null }), objects],
    ['a refused file', phase({ refusal: 'unreadable' }), objects],
    ['a file without rows', phase({ rowCount: 0 }), objects],
    ['no class', phase(), { ...objects, classId: undefined }],
    ['no column', phase(), { ...objects, matchColumn: undefined }],
    ['no field while matching by field', phase(), { ...objects, matchField: undefined }]
  ])('does not hold with %s', (_, givenPhase, givenObjects) => {
    expect(canContinue(givenPhase, givenObjects)).toBe(false)
  })
})

describe('keepMatchColumn', () => {
  const byPosition = { ...objects, matchColumn: '0' }

  it('keeps a column the file still has', () => {
    expect(keepMatchColumn(objects, inspected(), inspected())).toBe(objects)
  })

  it('forgets a column the file no longer has', () => {
    expect(keepMatchColumn(objects, inspected(), inspected({ columns: [{ dataIndex: '0', label: '0' }] })).matchColumn).toBeUndefined()
  })

  it('forgets a column another one took the position of', () => {
    const before = inspected({ columns: [{ dataIndex: '0', label: 'sku' }] })
    const after = inspected({ columns: [{ dataIndex: '0', label: 'price' }] })

    expect(keepMatchColumn(byPosition, before, after).matchColumn).toBeUndefined()
  })

  it('keeps a named column at its position on another sheet', () => {
    const columns = [{ dataIndex: '0', label: 'sku' }]

    expect(keepMatchColumn(byPosition, inspected({ columns, sheetName: 'Products' }), inspected({ columns, sheetName: 'Prices' }))).toBe(byPosition)
  })

  it('forgets a column without a header on another sheet, and keeps it on the same one', () => {
    const headerless = { columns: [{ dataIndex: '0', label: '0' }], hasHeaderRow: false }
    const before = inspected({ ...headerless, sheetName: 'Products' })

    expect(keepMatchColumn(byPosition, before, inspected({ ...headerless, sheetName: 'Prices' })).matchColumn).toBeUndefined()
    expect(keepMatchColumn(byPosition, before, inspected({ ...headerless, sheetName: 'Products' }))).toBe(byPosition)
  })

  it.each([
    ['delimiter', ',', ';'],
    ['enclosure', '"', "'"],
    ['escape', '\\', '"']
  ])('forgets a column without a header once the %s changes, and keeps it while it stays', (setting, was, now) => {
    const headerless = { columns: [{ dataIndex: '0', label: '0' }], hasHeaderRow: false }
    const before = inspected({ ...headerless, [setting]: was })

    expect(keepMatchColumn(byPosition, before, inspected({ ...headerless, [setting]: now })).matchColumn).toBeUndefined()
    expect(keepMatchColumn(byPosition, before, inspected({ ...headerless, [setting]: was }))).toBe(byPosition)
  })

  it('keeps a named column of a file split another way', () => {
    const before = inspected({ delimiter: ',' })

    expect(keepMatchColumn(objects, before, inspected({ delimiter: ';' }))).toBe(objects)
  })

  it('forgets a column once the first row stops or starts being the header', () => {
    const named = inspected({ columns: [{ dataIndex: '0', label: 'sku' }] })
    const unnamed = inspected({ columns: [{ dataIndex: '0', label: '0' }], hasHeaderRow: false })

    expect(keepMatchColumn(byPosition, named, unnamed).matchColumn).toBeUndefined()
    expect(keepMatchColumn(byPosition, unnamed, named).matchColumn).toBeUndefined()
  })

  it('forgets a column named like a position once the first row stops or starts being the header', () => {
    const named = inspected({ columns: [{ dataIndex: 'sku', label: 'sku' }, { dataIndex: '0', label: '0' }] })
    const unnamed = inspected({ columns: [{ dataIndex: '0', label: '0' }, { dataIndex: '1', label: '1' }], hasHeaderRow: false })

    expect(keepMatchColumn(byPosition, named, unnamed).matchColumn).toBeUndefined()
    expect(keepMatchColumn(byPosition, unnamed, named).matchColumn).toBeUndefined()
  })

  it('leaves no column as it is', () => {
    const none = { ...objects, matchColumn: undefined }
    expect(keepMatchColumn(none, inspected(), inspected())).toBe(none)
  })
})

describe('the accepted files', () => {
  it('go by the extension in any case', () => {
    expect(hasAcceptedExtension('prices.CSV')).toBe(true)
    expect(hasAcceptedExtension('northhike.xlsx')).toBe(true)
    expect(hasAcceptedExtension('northhike.xls')).toBe(false)
    expect(hasAcceptedExtension('northhike')).toBe(false)
  })

  it('refuse any other as the wrong format, with nothing kept', () => {
    expect(refusedForFormat()).toMatchObject({ refusal: 'wrong-format', columns: [], firstRows: [] })
    expect(refusedForFormat().fileId).toBeUndefined()
  })
})

describe('fileBadge', () => {
  it('shows the extension in capitals', () => {
    expect(fileBadge({ kind: 'uploading', file, loaded: 0, total: 1 })).toBe('CSV')
    expect(fileBadge({ kind: 'uploading', file: { name: 'a.b.xlsx', size: 1 }, loaded: 0, total: 1 })).toBe('XLSX')
  })
})
