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
  canContinueMapping,
  chooseField,
  createMapping,
  mappingBasis,
  missingRequiredFields,
  pendingSuggestionCount,
  restoreColumn,
  rowCounts,
  skipColumn,
  skippedRows,
  takeAllSuggestions,
  visibleRows,
  withSamples,
  type LoadedMapping,
  type Mapping,
  type MappingField
} from './mapping-state'
import { isTaken, takenFields } from './taken-fields'

const inspected: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 2,
  columns: [
    { dataIndex: 'Artikelnr', label: 'Artikelnr' },
    { dataIndex: 'Name DE', label: 'Name DE' },
    { dataIndex: 'Name EN', label: 'Name EN' },
    { dataIndex: 'Preis', label: 'Preis' },
    { dataIndex: 'Notiz', label: 'Notiz' }
  ],
  firstRows: [['NH-1', '', 'Jacket', null, 'x'], ['NH-2', 'Jacke', 'Coat', '12.50', 'y']]
}

const objects: ObjectsSettings = { classId: 'PROD', matchBy: 'field', matchField: 'sku', matchColumn: 'Artikelnr', newObjectsFolder: null }

const field = (key: string, given: Partial<MappingField> = {}): MappingField =>
  ({ key, title: key, localized: false, mandatory: false, transformationResultType: 'default', group: ['Product'], classificationStore: false, ...given })

const fields = [field('sku', { mandatory: true }), field('name', { localized: true, mandatory: true }), field('price'), field('ean', { mandatory: true })]

const suggestion = (column: string, target: string, score: number, language: string | null = null): LoadedMapping['suggestions'][number] => ({
  sourceIndex: column,
  sourceLabel: column,
  targetFieldName: target,
  targetFieldLabel: target,
  transformationResultType: 'default',
  score,
  language
})

const loaded: LoadedMapping = {
  fields,
  suggestions: [
    suggestion('Name DE', 'name', 100, 'de'),
    suggestion('Name EN', 'name', 100, 'en'),
    suggestion('Preis', 'price', 60),
    suggestion('Artikelnr', 'sku', 100)
  ]
}

const rowOf = (mapping: Mapping, column: string): Mapping['rows'][number] => mapping.rows.find((row) => row.column === column)!

describe('a new mapping', () => {
  const mapping = createMapping(inspected, objects, loaded)

  it('has a row per column in the order of the file, with its first value', () => {
    expect(mapping.rows.map(({ label }) => label)).toEqual(['Artikelnr', 'Name DE', 'Name EN', 'Preis', 'Notiz'])
    expect(rowOf(mapping, 'Name DE').sample).toBe('Jacke')
    expect(rowOf(mapping, 'Preis').sample).toBe('12.50')
  })

  it('takes the sure suggestions as automatic, each with its language', () => {
    expect(rowOf(mapping, 'Name DE')).toMatchObject({ choice: { field: 'name', language: 'de' }, auto: true })
    expect(rowOf(mapping, 'Name EN')).toMatchObject({ choice: { field: 'name', language: 'en' }, auto: true })
  })

  it('leaves a weaker suggestion waiting', () => {
    expect(rowOf(mapping, 'Preis')).toMatchObject({ choice: undefined, auto: false, suggestion: { field: 'price', score: 60 } })
    expect(pendingSuggestionCount(mapping)).toBe(1)
  })

  it('finds the objects with the match column, which goes into the field matched by and takes no suggestion', () => {
    expect(rowOf(mapping, 'Artikelnr')).toMatchObject({ isMatch: true, choice: { field: 'sku', language: null }, auto: false, suggestion: undefined })
  })

  it('gives the match column no field when matching by the object ID', () => {
    const byId = createMapping(inspected, { ...objects, matchBy: 'id' }, loaded)

    expect(rowOf(byId, 'Artikelnr')).toMatchObject({ isMatch: true, choice: undefined })
  })

  it('ignores a suggestion of a field it does not offer', () => {
    const other = createMapping(inspected, objects, { fields, suggestions: [suggestion('Notiz', 'path', 100)] })

    expect(rowOf(other, 'Notiz')).toMatchObject({ choice: undefined, suggestion: undefined })
  })

  it('gives a sure suggestion of a taken field only to the surest column', () => {
    const twice = createMapping(inspected, { ...objects, matchColumn: undefined }, {
      fields,
      suggestions: [suggestion('Preis', 'price', 100), suggestion('Notiz', 'price', 100), suggestion('Artikelnr', 'sku', 100)]
    })

    expect(rowOf(twice, 'Preis').choice?.field).toBe('price')
    expect(rowOf(twice, 'Notiz').choice).toBeUndefined()
    expect(rowOf(twice, 'Artikelnr').choice?.field).toBe('sku')
  })

  it('does not give the match field to another column', () => {
    const other = createMapping(inspected, objects, { fields, suggestions: [suggestion('Notiz', 'sku', 100)] })

    expect(rowOf(other, 'Notiz').choice).toBeUndefined()
  })
})

describe('changing the mapping', () => {
  const mapping = createMapping(inspected, objects, loaded)

  it('marks a field chosen by hand as no suggestion, even the suggested one', () => {
    const chosen = chooseField(mapping, 'Name DE', 'name')

    expect(rowOf(chosen, 'Name DE')).toMatchObject({ choice: { field: 'name', language: 'de' }, auto: false })
  })

  it('keeps the language only with its field', () => {
    const chosen = chooseField(mapping, 'Name DE', 'price')

    expect(rowOf(chosen, 'Name DE')).toMatchObject({ choice: { field: 'price', language: null }, auto: false })
  })

  it('can leave a column without a field', () => {
    expect(rowOf(chooseField(mapping, 'Name DE', undefined), 'Name DE')).toMatchObject({ choice: undefined, auto: false })
  })

  it('never changes the match column', () => {
    expect(rowOf(chooseField(mapping, 'Artikelnr', 'price'), 'Artikelnr').choice?.field).toBe('sku')
    expect(rowOf(skipColumn(mapping, 'Artikelnr'), 'Artikelnr').skipped).toBe(false)
  })

  it('moves a column not to import into its own area and frees its field', () => {
    const skipped = skipColumn(mapping, 'Name DE')

    expect(skippedRows(skipped).map(({ column }) => column)).toEqual(['Name DE'])
    expect(visibleRows(skipped, 'all', '').map(({ column }) => column)).not.toContain('Name DE')
    expect(isTaken(skipped.rows, { field: 'name', language: 'de' })).toBe(false)
  })

  it('restores a column without a field, its suggestion waiting again', () => {
    const restored = restoreColumn(skipColumn(mapping, 'Name DE'), 'Name DE')

    expect(rowOf(restored, 'Name DE')).toMatchObject({ skipped: false, choice: undefined, auto: false })
    expect(pendingSuggestionCount(restored)).toBe(2)
  })

  it('takes every waiting suggestion whose field is free', () => {
    const cleared = chooseField(chooseField(mapping, 'Name DE', undefined), 'Notiz', 'price')
    const taken = takeAllSuggestions(cleared)

    expect(rowOf(taken, 'Name DE')).toMatchObject({ choice: { field: 'name', language: 'de' }, auto: true })
    // its field went to another column by hand
    expect(rowOf(taken, 'Preis').choice).toBeUndefined()
    expect(rowOf(taken, 'Notiz')).toMatchObject({ choice: { field: 'price' }, auto: false })
    expect(pendingSuggestionCount(taken)).toBe(0)
  })

  it('does not take the suggestion of a skipped column', () => {
    expect(rowOf(takeAllSuggestions(skipColumn(mapping, 'Preis')), 'Preis').choice).toBeUndefined()
  })
})

describe('which field is taken', () => {
  const rows = createMapping(inspected, objects, loaded).rows

  it('is per field and language', () => {
    expect(isTaken(rows, { field: 'name', language: 'de' })).toBe(true)
    expect(isTaken(rows, { field: 'name', language: 'fr' })).toBe(false)
    expect(isTaken(rows, { field: 'price', language: null })).toBe(false)
  })

  it('does not count the column asking', () => {
    expect(isTaken(rows, { field: 'name', language: 'de' }, 'Name DE')).toBe(false)
  })
})

describe('the fields taken, looked up', () => {
  const rows = skipColumn(createMapping(inspected, objects, loaded), 'Name EN').rows
  const taken = takenFields(rows)
  it('are those found by searching the rows, without the column asking or a skipped one', () => {
    for (const choice of [{ field: 'name', language: 'de' }, { field: 'name', language: 'en' }, { field: 'sku', language: null }]) {
      for (const column of [undefined, 'Name DE', 'Artikelnr']) {
        expect(taken.has(choice, column)).toBe(isTaken(rows, choice, column))
      }
    }
    expect([taken.has({ field: 'name', language: 'de' }), taken.has({ field: 'name', language: 'de' }, 'Name DE')]).toEqual([true, false])
  })
})

describe('the same columns read again', () => {
  it('keep their choices and show the first values read now', () => {
    const mapping = chooseField(createMapping(inspected, objects, loaded), 'Notiz', 'price')
    const again = withSamples(mapping, { ...inspected, sheetName: 'Prices', firstRows: [['NH-9', 'Mantel', '', '99', 'z']] })

    expect(again.rows.map(({ choice }) => choice)).toEqual(mapping.rows.map(({ choice }) => choice))
    expect(again.rows.map(({ sample }) => sample)).toEqual(['NH-9', 'Mantel', undefined, '99', 'z'])
  })
})

describe('filtering the rows', () => {
  const mapping = skipColumn(createMapping(inspected, objects, loaded), 'Notiz')

  it('counts the imported columns, the mapped ones with the match column, and the open ones', () => {
    expect(rowCounts(mapping)).toEqual({ all: 4, mapped: 3, open: 1 })
  })

  it('shows the mapped or the open ones', () => {
    expect(visibleRows(mapping, 'mapped', '').map(({ column }) => column)).toEqual(['Artikelnr', 'Name DE', 'Name EN'])
    expect(visibleRows(mapping, 'open', '').map(({ column }) => column)).toEqual(['Preis'])
  })

  it('finds columns by a part of their name, in any case', () => {
    expect(visibleRows(mapping, 'all', ' name ').map(({ column }) => column)).toEqual(['Name DE', 'Name EN'])
    expect(visibleRows(mapping, 'open', 'name')).toEqual([])
  })
})

describe('the required fields', () => {
  const mapping = createMapping(inspected, objects, loaded)

  it('are those the class requires that no column goes into', () => {
    expect(missingRequiredFields(mapping, fields).map(({ key }) => key)).toEqual(['ean'])
  })

  it('count a localized field in any language, and the field matched by', () => {
    const one = skipColumn(mapping, 'Name EN')

    expect(missingRequiredFields(one, fields).map(({ key }) => key)).toEqual(['ean'])
    expect(missingRequiredFields(skipColumn(one, 'Name DE'), fields).map(({ key }) => key)).toEqual(['name', 'ean'])
  })
})

describe('going on', () => {
  const mapping = createMapping(inspected, objects, loaded)

  it('needs a column to import beside the match column', () => {
    expect(canContinueMapping(mapping)).toBe(true)

    const onlyMatch = ['Name DE', 'Name EN', 'Preis', 'Notiz'].reduce(skipColumn, mapping)
    expect(canContinueMapping(onlyMatch)).toBe(false)
    expect(canContinueMapping(chooseField(restoreColumn(onlyMatch, 'Notiz'), 'Notiz', 'price'))).toBe(true)
  })
})

describe('the basis of a mapping', () => {
  const basis = mappingBasis(inspected, objects)

  it('changes with the file, its columns, the class or what is matched by', () => {
    expect(mappingBasis({ ...inspected, fileId: 'file-2' }, objects)).not.toBe(basis)
    expect(mappingBasis({ ...inspected, columns: inspected.columns.slice(1) }, objects)).not.toBe(basis)
    expect(mappingBasis(inspected, { ...objects, classId: 'CAR' })).not.toBe(basis)
    expect(mappingBasis(inspected, { ...objects, matchBy: 'id' })).not.toBe(basis)
    expect(mappingBasis(inspected, { ...objects, matchField: 'ean' })).not.toBe(basis)
    expect(mappingBasis(inspected, { ...objects, matchColumn: 'Notiz' })).not.toBe(basis)
  })

  it('changes with the header row, and without one with the sheet or the way of splitting the rows', () => {
    const headerless: InspectedFile = { ...inspected, hasHeaderRow: false, sheetName: 'Products', delimiter: ';', enclosure: '"', escape: '\\' }
    const headerlessBasis = mappingBasis(headerless, objects)

    expect(headerlessBasis).not.toBe(basis)
    for (const changed of [{ sheetName: 'Prices' }, { delimiter: ',' }, { enclosure: "'" }, { escape: '' }]) {
      expect(mappingBasis({ ...headerless, ...changed }, objects)).not.toBe(headerlessBasis)
    }
    expect(mappingBasis({ ...inspected, sheetName: 'Prices' }, objects)).toBe(mappingBasis({ ...inspected, sheetName: 'Products' }, objects))
  })

  it('stays with the folder for new objects and the rows read', () => {
    expect(mappingBasis({ ...inspected, firstRows: [] }, { ...objects, newObjectsFolder: '/New' })).toBe(basis)
  })

  it('ignores a field left from matching by a field when matching by the object ID', () => {
    expect(mappingBasis(inspected, { ...objects, matchBy: 'id', matchField: 'ean' }))
      .toBe(mappingBasis(inspected, { ...objects, matchBy: 'id' }))
  })
})
