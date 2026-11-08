/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { collectUnmappedColumns, toMappingSuggestions } from './autofill-suggestions'

jest.mock('@pimcore/studio-ui-bundle/utils', () => {
  let next = 0
  return { uuid: () => `id-${next++}` }
}, { virtual: true })

describe('collectUnmappedColumns', () => {
  const columns = [
    { value: 'col_0', label: 'SKU' },
    { value: 'col_1', label: 'Bezeichnung DE' },
    { value: 'col_2', label: 'Price' }
  ]

  it('sends every column while nothing is mapped', () => {
    expect(collectUnmappedColumns(columns, [])).toEqual([
      { index: 'col_0', label: 'SKU' },
      { index: 'col_1', label: 'Bezeichnung DE' },
      { index: 'col_2', label: 'Price' }
    ])
  })

  it('leaves out each column a mapping row already reads, also from several sources', () => {
    expect(collectUnmappedColumns(columns, [
      { dataSourceIndex: ['col_0', 'col_2'] },
      {}
    ])).toEqual([{ index: 'col_1', label: 'Bezeichnung DE' }])
  })
})

describe('toMappingSuggestions', () => {
  const suggestion = {
    sourceIndex: 'col_1',
    sourceLabel: 'Bezeichnung DE',
    targetFieldName: 'bezeichnung',
    targetFieldLabel: 'Bezeichnung [bezeichnung]',
    transformationResultType: 'default',
    score: 100,
    language: 'de',
    source: 'name' as const
  }

  it('keeps what the server suggested and adds the value of the preview row', () => {
    const [result] = toMappingSuggestions([suggestion], [
      { dataIndex: 'col_0', label: 'SKU', value: 'A-1' },
      { dataIndex: 'col_1', label: 'Bezeichnung DE', value: 'Stuhl' }
    ])

    expect(result).toMatchObject({ ...suggestion, previewResult: 'Stuhl' })
  })

  it('has no preview value for a column the preview row lacks', () => {
    const [result] = toMappingSuggestions([suggestion], [])

    expect(result.previewResult).toBeNull()
  })

  it('gives each suggestion its own id for the selection', () => {
    const results = toMappingSuggestions([suggestion, { ...suggestion, sourceIndex: 'col_2' }], [])

    expect(results[0].id).not.toEqual(results[1].id)
  })
})
