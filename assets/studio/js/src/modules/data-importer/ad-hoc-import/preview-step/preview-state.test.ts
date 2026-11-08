/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'
import { type ImportConfiguration } from '../mapping-step/configuration-state'
import { type Mapping, type MappingRow } from '../mapping-step/mapping-state'
import {
  cellText,
  dryRunParameters,
  hasErrors,
  lastPageWithin,
  pageOf,
  previewCounts,
  rowTone,
  visibleRows,
  type DryRun,
  type DryRunRow
} from './preview-state'

const target = { folderId: 77, folderPath: '/Products/Outdoor', folderLabel: 'Home' }

const inspected: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  delimiter: ';',
  detectedDelimiter: ';',
  enclosure: '"',
  escape: '\\',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 3,
  columns: [
    { dataIndex: 'SKU', label: 'SKU', position: 0 },
    { dataIndex: 'Name', label: 'Name', position: 1 },
    { dataIndex: 'Notes', label: 'Notes', position: 2 }
  ],
  firstRows: []
}

const objects: ObjectsSettings = {
  classId: 'PROD',
  matchBy: 'field',
  matchField: 'sku',
  matchColumn: 'SKU',
  newObjectsFolder: null
}

const mappingRow = (given: Partial<MappingRow>): MappingRow => ({
  column: 'SKU',
  label: 'SKU',
  auto: false,
  skipped: false,
  isMatch: false,
  transformations: [],
  ...given
})

const mapping: Mapping = {
  basis: 'any',
  rows: [
    mappingRow({ isMatch: true, choice: { field: 'sku', language: null } }),
    mappingRow({
      column: 'Name',
      label: 'Name',
      choice: { field: 'name', language: 'de' },
      transformations: [{ type: 'trim', settings: { mode: 'both' } }]
    }),
    mappingRow({ column: 'Notes', label: 'Notes', skipped: true, choice: undefined })
  ]
}

const configuration: ImportConfiguration = {
  name: 'products',
  classId: 'CAR',
  className: 'Car',
  matchBy: 'id',
  matchField: null,
  matchLanguage: null,
  matchColumn: 'sku',
  newObjectsFolder: '/Cars',
  columns: ['sku', 'name', 'color'],
  columnsBy: 'name',
  unusable: null
}

const row = (given: Partial<DryRunRow>): DryRunRow => ({
  row: 2,
  matchValue: 'NH-1',
  action: 'update',
  error: null,
  values: [],
  invalid: [],
  blocked: false,
  ...given
})

const updated = row({ row: 2, values: [{ old: '179.90', new: '189.90' }, { old: 'red', new: 'red' }] })
const created = row({ row: 3, action: 'create', values: [{ old: null, new: '129.00' }, { old: null, new: null }] })
const unmatched = row({ row: 4, action: null, error: '2 objects have SKU NH-9', values: [{ old: null, new: '72.00' }, { old: null, new: 'grey' }] })
const invalidPrice = row({
  row: 5,
  values: [{ old: '1.00', new: '12,5,0' }, { old: 'red', new: 'blue' }],
  invalid: [{ field: 0, label: 'Price', value: '12,5,0', message: 'Price “12,5,0” is no number' }]
})

const dryRun: DryRun = {
  matchColumn: 'SKU',
  newObjectsFolder: '/Products/Outdoor',
  fields: [{ key: 'price', language: null, label: 'Price' }, { key: 'color', language: null, label: 'Color' }],
  rows: [updated, created, unmatched, invalidPrice]
}

describe('the parameters of a dry run', () => {
  it('send the columns mapped here, the column matched by included, with the file settings and the objects', () => {
    expect(dryRunParameters({ target, inspected, objects, mapWith: 'here', configuration: undefined, mapping })).toEqual({
      delimiter: ';',
      enclosure: '"',
      escape: '\\',
      hasHeaderRow: true,
      sheetName: undefined,
      folderId: 77,
      classId: 'PROD',
      matchBy: 'field',
      matchColumn: 'SKU',
      matchField: 'sku',
      matchLanguage: null,
      newObjectsFolder: null,
      columns: [
        { column: 'SKU', field: 'sku', language: null, transformationPipeline: [] },
        { column: 'Name', field: 'name', language: 'de', transformationPipeline: [{ type: 'trim', settings: { mode: 'both' } }] }
      ]
    })
  })

  it('send the key of a classification store a column goes into', () => {
    const withStore: Mapping = {
      basis: 'any',
      rows: [
        mappingRow({ isMatch: true, choice: { field: 'sku', language: null } }),
        mappingRow({
          column: 'Weight',
          label: 'Weight',
          choice: { field: 'attributes', language: null, key: { id: '1-2', label: 'Basics › Weight' } }
        })
      ]
    }

    expect(dryRunParameters({ target, inspected, objects, mapWith: 'here', configuration: undefined, mapping: withStore }).columns).toEqual([
      { column: 'SKU', field: 'sku', language: null, transformationPipeline: [] },
      { column: 'Weight', field: 'attributes', language: null, key: '1-2', transformationPipeline: [] }
    ])
  })

  it('send no field to match by when the objects are found by their id', () => {
    const parameters = dryRunParameters({
      target,
      inspected,
      objects: { ...objects, matchBy: 'id' },
      mapWith: 'here',
      configuration: undefined,
      mapping: null
    })

    expect(parameters.matchBy).toBe('id')
    expect(parameters.matchField).toBeNull()
    expect(parameters.columns).toEqual([])
  })

  it('send a configuration with its class, match and folder and the file\'s key of each column it reads', () => {
    const csv = { ...inspected, columns: [...inspected.columns.slice(0, 2)] }
    const parameters = dryRunParameters({ target, inspected: csv, objects, mapWith: 'configuration', configuration, mapping })

    expect(parameters).toMatchObject({
      classId: 'CAR',
      matchBy: 'id',
      matchField: null,
      newObjectsFolder: '/Cars',
      configuration: 'products',
      configurationColumns: {}
    })
    expect(parameters.columns).toBeUndefined()

    const byName = dryRunParameters({
      target,
      inspected: { ...csv, columns: [{ dataIndex: 'sku', label: 'sku', position: 0 }, { dataIndex: 'name', label: 'name', position: 1 }] },
      objects,
      mapWith: 'configuration',
      configuration,
      mapping
    })
    expect(byName.matchColumn).toBe('sku')
    expect(byName.configurationColumns).toEqual({ sku: 'sku', name: 'name' })
  })
})

describe('the preview of a dry run', () => {
  it('counts what skipping the rows with errors imports', () => {
    expect(previewCounts(dryRun, 'skip')).toEqual({ updated: 1, created: 1, withErrors: 2, imported: 2 })
  })

  it('counts a row whose values its fields cannot take as imported without them, a row without an object never', () => {
    expect(previewCounts(dryRun, 'values')).toEqual({ updated: 2, created: 1, withErrors: 2, imported: 3 })
  })

  it('never counts a row a save would refuse anyway as imported', () => {
    const blocked = row({
      invalid: [{ field: null, label: 'Basics › height', value: '', message: 'Basics › height is required' }],
      blocked: true
    })
    const counts = previewCounts({ ...dryRun, rows: [blocked] }, 'values')

    expect(counts).toEqual({ updated: 0, created: 0, withErrors: 1, imported: 0 })
    expect(rowTone(blocked, 'values')).toBe('error')
  })

  it('tells the rows with errors', () => {
    expect(dryRun.rows.map(hasErrors)).toEqual([false, false, true, true])
    expect(visibleRows(dryRun, true)).toEqual([unmatched, invalidPrice])
    expect(visibleRows(dryRun, false)).toEqual(dryRun.rows)
  })

  it('marks a skipped row as an error and one imported without values as a warning', () => {
    expect(dryRun.rows.map((each) => rowTone(each, 'skip'))).toEqual(['none', 'none', 'error', 'error'])
    expect(dryRun.rows.map((each) => rowTone(each, 'values'))).toEqual(['none', 'none', 'error', 'warning'])
  })

  it('shows a changed value with the one before, an unchanged one and the file\'s value as they are', () => {
    expect(cellText(updated, 0)).toBe('179.90 → 189.90')
    expect(cellText(updated, 1)).toBe('red')
    expect(cellText(created, 0)).toBe('— → 129.00')
    expect(cellText(created, 1)).toBe('')
    expect(cellText(unmatched, 0)).toBe('72.00')
    expect(cellText(invalidPrice, 0)).toBe('12,5,0')
    expect(cellText(invalidPrice, 1)).toBe('red → blue')
    expect(cellText(updated, 5)).toBe('')
  })

  it('pages the rows and keeps a page within those left', () => {
    const rows = Array.from({ length: 250 }, (_, index) => index)

    expect(pageOf(rows, 1, 100)).toHaveLength(100)
    expect(pageOf(rows, 3, 100)).toEqual(rows.slice(200))
    expect(lastPageWithin(3, 250, 100)).toBe(3)
    expect(lastPageWithin(3, 40, 100)).toBe(1)
    expect(lastPageWithin(2, 0, 100)).toBe(1)
  })
})
