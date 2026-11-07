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
  canGoInto,
  chooseField,
  chooseLanguage,
  chooseStoreKey,
  createMapping,
  isTaken,
  withLanguages,
  type LoadedMapping,
  type Mapping,
  type MappingField
} from './mapping-state'

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

describe('a classification store', () => {
  const mapping = createMapping(inspected, objects, loaded)
  const width = { id: '2-4', label: 'Dimensions › Width' }
  const height = { id: '2-5', label: 'Dimensions › Height' }

  it('goes in by the key chosen, by hand', () => {
    const next = chooseStoreKey(mapping, 'Notiz', 'attributes', width)

    expect(rowOf(next, 'Notiz').choice).toEqual({ field: 'attributes', language: null, key: width })
    expect(rowOf(next, 'Notiz').auto).toBe(false)
  })

  it('keeps its language when another key of the same store is chosen, and drops it for another store', () => {
    const inGerman = chooseLanguage(chooseStoreKey(mapping, 'Notiz', 'attributes', width), 'Notiz', 'de')

    expect(rowOf(chooseStoreKey(inGerman, 'Notiz', 'attributes', height), 'Notiz').choice?.language).toBe('de')
    expect(rowOf(chooseStoreKey(inGerman, 'Notiz', 'features', height), 'Notiz').choice?.language).toBeNull()
  })

  it('takes a column per key', () => {
    const rows = chooseStoreKey(mapping, 'Notiz', 'attributes', width).rows

    expect(isTaken(rows, { field: 'attributes', language: null, key: width })).toBe(true)
    expect(isTaken(rows, { field: 'attributes', language: null, key: height })).toBe(false)
    expect(isTaken(rows, { field: 'attributes', language: null })).toBe(false)
  })

  it('never changes the match column', () => {
    expect(chooseStoreKey(mapping, 'Artikelnr', 'attributes', width)).toEqual(mapping)
  })
})

describe('the language of a column', () => {
  const mapping = createMapping(inspected, objects, loaded)

  it('can be changed', () => {
    expect(rowOf(chooseLanguage(mapping, 'Name DE', 'fr'), 'Name DE').choice).toEqual({ field: 'name', language: 'fr' })
  })

  it('stays without a field', () => {
    expect(rowOf(chooseLanguage(mapping, 'Notiz', 'fr'), 'Notiz').choice).toBeUndefined()
  })
})

describe('giving localized fields a language', () => {
  const languages = ['en', 'de', 'fr']
  const fieldsWithStore = [...fields, field('attributes', { localized: true, classificationStore: true })]
  const noGuess = createMapping(inspected, objects, { fields, suggestions: [] })

  it('takes the language guessed from the column name', () => {
    const guessed = createMapping(inspected, objects, { fields, suggestions: [suggestion('Name DE', 'price', 40, 'de')] })

    expect(rowOf(withLanguages(chooseField(guessed, 'Name DE', 'name'), fields, languages), 'Name DE').choice?.language).toBe('de')
  })

  it('takes the first language no other column has for the field', () => {
    const both = chooseField(chooseField(noGuess, 'Name DE', 'name'), 'Name EN', 'name')
    const next = withLanguages(both, fields, languages)

    expect(rowOf(next, 'Name DE').choice?.language).toBe('en')
    expect(rowOf(next, 'Name EN').choice?.language).toBe('de')
  })

  it('takes the first free language when the guessed one is taken', () => {
    const guessed = createMapping(inspected, objects, { fields, suggestions: [suggestion('Name DE', 'price', 40, 'de')] })
    const germanTaken = chooseLanguage(chooseField(guessed, 'Notiz', 'name'), 'Notiz', 'de')

    expect(rowOf(withLanguages(chooseField(germanTaken, 'Name DE', 'name'), fields, languages), 'Name DE').choice?.language).toBe('en')
  })

  it('takes the first language when every one is taken', () => {
    const one = withLanguages(chooseField(chooseField(noGuess, 'Name DE', 'name'), 'Name EN', 'name'), fields, ['en'])

    expect(rowOf(one, 'Name EN').choice?.language).toBe('en')
  })

  it('gives a localized classification store a language too', () => {
    const next = withLanguages(chooseStoreKey(noGuess, 'Notiz', 'attributes', { id: '2-4', label: 'Width' }), fieldsWithStore, languages)

    expect(rowOf(next, 'Notiz').choice?.language).toBe('en')
  })

  it('leaves other fields, skipped columns and the mapping without languages as they are', () => {
    const priced = chooseField(noGuess, 'Notiz', 'price')

    expect(withLanguages(priced, fields, languages)).toBe(priced)
    expect(withLanguages(chooseField(noGuess, 'Notiz', 'name'), fields, [])).toEqual(chooseField(noGuess, 'Notiz', 'name'))
    const skipped = { ...noGuess, rows: noGuess.rows.map((row) => row.column === 'Notiz' ? { ...row, skipped: true, choice: { field: 'name', language: null } } : row) }
    expect(withLanguages(skipped, fields, languages)).toBe(skipped)
  })
})

describe('which field a column can still go into', () => {
  const languages = ['en', 'de']
  const mapping = createMapping(inspected, objects, loaded)

  it('is a field no other column goes into', () => {
    const rows = chooseField(mapping, 'Notiz', 'price').rows

    expect(canGoInto(rows, field('price'), 'Preis', languages)).toBe(false)
    expect(canGoInto(rows, field('price'), 'Notiz', languages)).toBe(true)
    expect(canGoInto(rows, field('ean'), 'Preis', languages)).toBe(true)
  })

  it('is a localized field while one of its languages is free', () => {
    expect(canGoInto(mapping.rows, field('name', { localized: true }), 'Notiz', languages)).toBe(false)
    expect(canGoInto(mapping.rows, field('name', { localized: true }), 'Notiz', [...languages, 'fr'])).toBe(true)
    expect(canGoInto(mapping.rows, field('name', { localized: true }), 'Notiz', [])).toBe(true)
  })

  it('is always a classification store, whose keys take a column each', () => {
    const rows = chooseStoreKey(mapping, 'Notiz', 'attributes', { id: '2-4', label: 'Width' }).rows

    expect(canGoInto(rows, field('attributes', { classificationStore: true }), 'Preis', languages)).toBe(true)
  })
})
