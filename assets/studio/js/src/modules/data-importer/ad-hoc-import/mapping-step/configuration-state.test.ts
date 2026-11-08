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
import {
  canContinueWithConfiguration,
  columnLabel,
  configurationColumnLabel,
  fileColumn,
  findConfiguration,
  importObjects,
  missingColumns,
  unusableReason,
  type ImportConfiguration
} from './configuration-state'

const inspected: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'xlsx',
  hasHeaderRow: true,
  sheets: ['Products'],
  rowCount: 2,
  columns: [{ dataIndex: '0', label: 'SKU' }, { dataIndex: '1', label: 'Name DE' }],
  firstRows: []
}

const configuration = (given: Partial<ImportConfiguration> = {}): ImportConfiguration => ({
  name: 'products',
  classId: 'PROD',
  className: 'Product',
  matchBy: 'field',
  matchField: 'name',
  matchLanguage: 'de',
  matchColumn: '1',
  newObjectsFolder: '/Products/Imported',
  columns: ['1', '0'],
  columnsBy: 'position',
  unusable: null,
  ...given
})

// keyed by name
const csvWithHeader: InspectedFile = {
  ...inspected,
  type: 'csv',
  sheets: [],
  columns: [{ dataIndex: 'SKU', label: 'SKU', position: 0 }, { dataIndex: 'Name DE ', label: 'Name DE', position: 1 }]
}

// keyed by position, without names
const xlsxWithoutHeader: InspectedFile = {
  ...inspected,
  hasHeaderRow: false,
  columns: [{ dataIndex: '0', label: '0' }, { dataIndex: '1', label: '1' }]
}

const byName = (given: Partial<ImportConfiguration> = {}): ImportConfiguration =>
  configuration({ columnsBy: 'name', matchColumn: 'SKU', columns: ['SKU', 'Name DE'], ...given })

const objects: ObjectsSettings = { classId: 'CAR', matchBy: 'field', matchField: 'vin', matchColumn: '0', newObjectsFolder: null }

describe('a configuration', () => {
  it('can be used when the server allows it and the file has its match column', () => {
    expect(unusableReason(configuration(), inspected)).toBeNull()
    expect(canContinueWithConfiguration(configuration(), inspected)).toBe(true)
  })

  it('cannot be used for the reason the server gives', () => {
    const closed = configuration({ unusable: 'folder-not-allowed', matchColumn: 'Gone' })

    expect(unusableReason(closed, inspected)).toBe('folder-not-allowed')
    expect(canContinueWithConfiguration(closed, inspected)).toBe(false)
  })

  it('cannot be used when the file lacks the column it matches by', () => {
    const elsewhere = configuration({ matchColumn: 'Artikelnr' })

    expect(unusableReason(elsewhere, inspected)).toBe('match-column')
    expect(canContinueWithConfiguration(elsewhere, inspected)).toBe(false)
  })

  it('cannot be gone on with before it is chosen or without a file', () => {
    expect(canContinueWithConfiguration(undefined, inspected)).toBe(false)
    expect(canContinueWithConfiguration(configuration(), undefined)).toBe(false)
  })

  it('names the columns it reads that the file does not have', () => {
    expect(missingColumns(configuration({ columns: ['1', 'Price', '0', 'Weight'] }), inspected)).toEqual(['Price', 'Weight'])
    expect(missingColumns(configuration(), inspected)).toEqual([])
  })

  it('is found by its name', () => {
    const list = [configuration({ name: 'prices' }), configuration()]

    expect(findConfiguration(list, 'products')).toBe(list[1])
    expect(findConfiguration(list, 'gone')).toBeUndefined()
    expect(findConfiguration(list, undefined)).toBeUndefined()
    expect(findConfiguration(undefined, 'products')).toBeUndefined()
  })
})

describe('a column the configuration reads', () => {
  it('is the same key when the configuration and the file key columns alike', () => {
    expect(fileColumn(configuration(), inspected, '1')).toBe('1')
    expect(fileColumn(configuration(), inspected, '2')).toBeUndefined()
    expect(fileColumn(byName(), csvWithHeader, 'SKU')).toBe('SKU')
    expect(fileColumn(byName(), csvWithHeader, 'Price')).toBeUndefined()
  })

  it('is found by its header name in a file keyed by position', () => {
    expect(fileColumn(byName(), inspected, 'SKU')).toBe('0')
    expect(fileColumn(byName(), inspected, 'Name DE ')).toBe('1')
    expect(fileColumn(byName(), inspected, 'Price')).toBeUndefined()
  })

  it('is not found by a name the header gives two columns', () => {
    const twice: InspectedFile = { ...inspected, columns: [{ dataIndex: '0', label: 'SKU' }, { dataIndex: '1', label: 'SKU' }] }

    expect(fileColumn(byName(), twice, 'SKU')).toBeUndefined()
    expect(unusableReason(byName(), twice)).toBe('match-column')
  })

  it('is not found by name in a file without a header row', () => {
    expect(fileColumn(byName(), xlsxWithoutHeader, 'SKU')).toBeUndefined()
    expect(fileColumn(byName(), xlsxWithoutHeader, '0')).toBeUndefined()
  })

  it('is found by its position in a file keyed by name', () => {
    expect(fileColumn(configuration(), csvWithHeader, '0')).toBe('SKU')
    expect(fileColumn(configuration(), csvWithHeader, '1')).toBe('Name DE ')
    expect(fileColumn(configuration(), csvWithHeader, '2')).toBeUndefined()
    expect(fileColumn(configuration(), csvWithHeader, '01')).toBeUndefined()
    expect(fileColumn(configuration(), csvWithHeader, 'SKU')).toBeUndefined()
  })

  it('is found by its place in the file when the header row leaves a column unnamed', () => {
    // header `SKU,,Name`: the unnamed column has no key, so it is not among the columns
    const unnamedSecond: InspectedFile = {
      ...csvWithHeader,
      columns: [{ dataIndex: 'SKU', label: 'SKU', position: 0 }, { dataIndex: 'Name', label: 'Name', position: 2 }]
    }

    expect(fileColumn(configuration(), unnamedSecond, '0')).toBe('SKU')
    expect(fileColumn(configuration(), unnamedSecond, '1')).toBeUndefined()
    expect(fileColumn(configuration(), unnamedSecond, '2')).toBe('Name')
    expect(unusableReason(configuration({ matchColumn: '1' }), unnamedSecond)).toBe('match-column')
  })

  it('is named by its label in the file, or as the configuration names it', () => {
    expect(configurationColumnLabel(byName(), inspected, 'SKU')).toBe('SKU')
    expect(configurationColumnLabel(configuration(), csvWithHeader, '1')).toBe('Name DE')
    expect(configurationColumnLabel(configuration(), csvWithHeader, '7')).toBe('7')
    expect(configurationColumnLabel(configuration(), undefined, '1')).toBe('1')
  })
})

describe('a configuration keying columns otherwise than the file', () => {
  it('can be used with an XLSX file that has its match column by name', () => {
    expect(unusableReason(byName(), inspected)).toBeNull()
    expect(missingColumns(byName({ columns: ['SKU', 'Name DE', 'Price'] }), inspected)).toEqual(['Price'])
  })

  it('can be used by position with a CSV file that has a header row', () => {
    expect(unusableReason(configuration({ matchColumn: '0' }), csvWithHeader)).toBeNull()
    expect(missingColumns(configuration({ columns: ['0', '1', '4'] }), csvWithHeader)).toEqual(['4'])
  })

  it('cannot be used when the file has no column at its match position', () => {
    expect(unusableReason(configuration({ matchColumn: '5' }), csvWithHeader)).toBe('match-column')
  })

  it('cannot match by name in a file without a header row', () => {
    expect(unusableReason(byName(), xlsxWithoutHeader)).toBe('match-column')
  })
})

describe('a column', () => {
  it('is named by its label in the file, or by itself when the file lacks it', () => {
    expect(columnLabel(inspected, '1')).toBe('Name DE')
    expect(columnLabel(inspected, 'Price')).toBe('Price')
  })
})

describe('what the import works with', () => {
  it('is the file step\'s objects when the columns are mapped here', () => {
    expect(importObjects(objects, 'here', configuration(), inspected)).toEqual({ ...objects, matchLanguage: null })
  })

  it('is the file step\'s objects while no configuration is chosen', () => {
    expect(importObjects(objects, 'configuration', undefined, inspected)).toEqual({ ...objects, matchLanguage: null })
    expect(importObjects(objects, 'configuration', configuration(), undefined)).toEqual({ ...objects, matchLanguage: null })
  })

  it('takes the class, the match and the folder of a chosen configuration instead', () => {
    expect(importObjects(objects, 'configuration', configuration(), inspected)).toEqual({
      classId: 'PROD',
      matchBy: 'field',
      matchField: 'name',
      matchLanguage: 'de',
      matchColumn: '1',
      newObjectsFolder: '/Products/Imported'
    })
  })

  it('matches by the object ID of a configuration that does', () => {
    expect(importObjects(objects, 'configuration', configuration({
      matchBy: 'id',
      matchField: null,
      matchLanguage: null,
      classId: null,
      matchColumn: null
    }), inspected)).toEqual({
      classId: undefined,
      matchBy: 'id',
      matchField: undefined,
      matchLanguage: null,
      matchColumn: undefined,
      newObjectsFolder: '/Products/Imported'
    })
  })

  it('matches by the key the file has for the match column of the configuration', () => {
    expect(importObjects(objects, 'configuration', byName(), inspected).matchColumn).toBe('0')
    expect(importObjects(objects, 'configuration', configuration({ matchColumn: '1' }), csvWithHeader).matchColumn).toBe('Name DE ')
  })
})
