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
  unusable: null,
  ...given
})

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

describe('a column', () => {
  it('is named by its label in the file, or by itself when the file lacks it', () => {
    expect(columnLabel(inspected, '1')).toBe('Name DE')
    expect(columnLabel(inspected, 'Price')).toBe('Price')
  })
})

describe('what the import works with', () => {
  it('is the file step\'s objects when the columns are mapped here', () => {
    expect(importObjects(objects, 'here', configuration())).toEqual({ ...objects, matchLanguage: null })
  })

  it('is the file step\'s objects while no configuration is chosen', () => {
    expect(importObjects(objects, 'configuration', undefined)).toEqual({ ...objects, matchLanguage: null })
  })

  it('takes the class, the match and the folder of a chosen configuration instead', () => {
    expect(importObjects(objects, 'configuration', configuration())).toEqual({
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
    }))).toEqual({
      classId: undefined,
      matchBy: 'id',
      matchField: undefined,
      matchLanguage: null,
      matchColumn: undefined,
      newObjectsFolder: '/Products/Imported'
    })
  })
})
