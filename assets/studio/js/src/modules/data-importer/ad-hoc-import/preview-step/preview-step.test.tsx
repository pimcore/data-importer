/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { PreviewStep } from './preview-step'
import { type DryRun, type DryRunRow } from './preview-state'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: { error: 'error', warning: 'warning' } })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)

const row = (given: Partial<DryRunRow>): DryRunRow => ({
  row: 2,
  matchValue: 'NH-1001',
  action: 'update',
  error: null,
  values: [{ old: '179.90', new: '189.90' }, { old: 'red', new: 'red' }],
  invalid: [],
  blocked: false,
  ...given
})

const rows: DryRunRow[] = [
  row({}),
  row({ row: 3, matchValue: 'NH-1077', action: 'create', values: [{ old: null, new: '129.00' }, { old: null, new: 'green' }] }),
  row({
    row: 4,
    matchValue: 'NH-1003',
    values: [{ old: '10.00', new: '12,5,0' }, { old: 'red', new: 'red' }],
    invalid: [{ field: 0, label: 'Price', value: '12,5,0', message: 'Price “12,5,0” is no number' }]
  }),
  row({
    row: 5,
    matchValue: 'NH-1002',
    action: null,
    error: 'SKU NH-1002 is in the file twice (row 3)',
    values: [{ old: null, new: '99.00' }, { old: null, new: 'navy' }]
  })
]

const dryRun = (given: DryRunRow[] = rows): DryRun => ({
  matchColumn: 'SKU',
  newObjectsFolder: '/Products/Outdoor',
  fields: [{ key: 'price', language: null, label: 'Price' }, { key: 'title', language: 'de', label: 'Title' }],
  rows: given
})

const cells = (): string[][] => within(screen.getByRole('table')).getAllByRole('row').slice(1)
  .map((tableRow) => within(tableRow).getAllByRole('cell').map((cell) => cell.textContent ?? ''))

afterEach(cleanup)

describe('the preview step', () => {
  it('shows that it checks until the dry run answered', () => {
    render(<PreviewStep
      dryRun={ null }
      errorMode="skip"
           />)

    expect(document.querySelector('[aria-busy="true"]')).not.toBeNull()
    expect(screen.queryByRole('table')).toBeNull()
  })

  it('counts what skipping the rows with errors does and lists every row with its action and values', () => {
    render(<PreviewStep
      dryRun={ dryRun() }
      errorMode="skip"
           />)

    expect(screen.getByText('will be updated').previousSibling?.textContent).toBe('1')
    expect(screen.getByText('will be created in /Products/Outdoor').previousSibling?.textContent).toBe('1')
    expect(screen.getByText('rows with errors: skipped, downloadable afterwards').previousSibling?.textContent).toBe('2')
    expect(screen.getByText('All 4 rows checked')).toBeTruthy()
    expect(within(screen.getByRole('table')).getAllByRole('columnheader').map((cell) => cell.textContent))
      .toEqual(['Row', 'SKU', 'Action', 'Price', 'Title (de)', 'Message'])
    expect(cells()).toEqual([
      ['2', 'NH-1001', 'update', '179.90 → 189.90', 'red', ''],
      ['3', 'NH-1077', 'create', '— → 129.00', '— → green', ''],
      ['4', 'NH-1003', 'skipped', '12,5,0', 'red', 'Price “12,5,0” is no number'],
      ['5', 'NH-1002', 'skipped', '99.00', 'navy', 'SKU NH-1002 is in the file twice (row 3)']
    ])
  })

  it('imports a row without the values its fields cannot take when so chosen, and still skips one without an object', () => {
    render(<PreviewStep
      dryRun={ dryRun() }
      errorMode="values"
           />)

    expect(screen.getByText('will be updated').previousSibling?.textContent).toBe('2')
    expect(screen.getByText('rows with errors: invalid values left out; rows that cannot be matched or saved are skipped')).toBeTruthy()
    expect(cells()[2]).toEqual(['4', 'NH-1003', 'update, without Price', '12,5,0', 'red', 'Price “12,5,0” → left unchanged'])
    expect(cells()[3][2]).toBe('skipped')
  })

  it('says why a row a save would refuse anyway is skipped, also when invalid values are left out', () => {
    const blocked = row({
      row: 6,
      invalid: [{ field: null, label: 'Basics › height', value: '', message: 'Basics › height is required' }],
      blocked: true
    })

    render(<PreviewStep
      dryRun={ { ...dryRun(), rows: [blocked] } }
      errorMode="values"
           />)

    expect(cells()[0][2]).toBe('skipped')
    expect(cells()[0][5]).toBe('Basics › height is required')
  })

  it('marks the message of a skipped row as an error and of a row imported without values as a warning', () => {
    const tones = (): Array<string | null> => within(screen.getByRole('table')).getAllByRole('row').slice(1)
      .map((tableRow) => within(tableRow).getAllByRole('cell')[5].querySelector('span')?.getAttribute('class') ?? null)

    render(<PreviewStep
      dryRun={ dryRun() }
      errorMode="skip"
           />)
    expect(tones()).toEqual([null, null, 'error', 'error'])
    cleanup()

    render(<PreviewStep
      dryRun={ dryRun() }
      errorMode="values"
           />)
    expect(tones()).toEqual([null, null, 'warning', 'error'])
  })

  it('shows only the rows with errors when asked to', () => {
    render(<PreviewStep
      dryRun={ dryRun() }
      errorMode="skip"
           />)

    fireEvent.click(screen.getByLabelText('Errors only'))

    expect(cells().map((cellsOfRow) => cellsOfRow[0])).toEqual(['4', '5'])
    expect(screen.getByText('Total 2 items')).toBeTruthy()
  })

  it('pages the rows, a hundred at a time, and goes back to a page that still has rows', () => {
    const many = Array.from({ length: 150 }, (_, index) => row({ row: index + 2, matchValue: `NH-${index}` }))
    many[140] = row({ row: 142, action: null, error: 'sku is empty', values: [] })
    render(<PreviewStep
      dryRun={ dryRun(many) }
      errorMode="skip"
           />)

    expect(cells()).toHaveLength(100)
    expect(screen.getByText('Total 150 items')).toBeTruthy()

    fireEvent.click(screen.getByRole('button', { name: 'next page' }))

    expect(cells()).toHaveLength(50)
    expect(cells()[0][0]).toBe('102')

    fireEvent.click(screen.getByLabelText('Errors only'))

    expect(cells().map((cellsOfRow) => cellsOfRow[0])).toEqual(['142'])
    expect(screen.getByText('1 / 1')).toBeTruthy()
  })
})
