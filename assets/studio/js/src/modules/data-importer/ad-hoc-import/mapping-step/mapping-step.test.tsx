/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useState } from 'react'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'
import { createMapping, type LoadedMapping, type Mapping } from './mapping-state'
import { MappingStep } from './mapping-step'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)

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
    { dataIndex: 'Preis', label: 'Preis' },
    { dataIndex: 'Notiz', label: 'Notiz' }
  ],
  firstRows: [['NH-1', 'Jacke', '12.50', 'Größe M & L']]
}

const objects: ObjectsSettings = { classId: 'PROD', matchBy: 'field', matchField: 'sku', matchColumn: 'Artikelnr', newObjectsFolder: null }

const field = (key: string, title: string, given: Partial<LoadedMapping['fields'][number]> = {}): LoadedMapping['fields'][number] =>
  ({ key, title, localized: false, mandatory: false, transformationResultType: 'default', ...given })

const loaded: LoadedMapping = {
  fields: [
    field('sku', 'SKU'),
    field('name', 'Name', { localized: true }),
    field('price', 'Price'),
    field('note', 'Note & remark'),
    field('ean', 'EAN', { mandatory: true })
  ],
  suggestions: [
    { sourceIndex: 'Name DE', sourceLabel: 'Name DE', targetFieldName: 'name', targetFieldLabel: 'Name', transformationResultType: 'default', score: 100, language: 'de' },
    { sourceIndex: 'Preis', sourceLabel: 'Preis', targetFieldName: 'price', targetFieldLabel: 'Price', transformationResultType: 'default', score: 55, language: null }
  ]
}

const Stateful = ({ initial, isLoading = false }: { initial: Mapping | null, isLoading?: boolean }): React.JSX.Element => {
  const [mapping, setMapping] = useState(initial)

  return (
    <MappingStep
      fields={ loaded.fields }
      isLoading={ isLoading }
      mapping={ mapping }
      onChange={ setMapping }
    />
  )
}

const renderStep = (): void => {
  render(<Stateful initial={ createMapping(inspected, objects, loaded) } />)
}

const fieldOf = (column: string): HTMLSelectElement => screen.getByLabelText<HTMLSelectElement>(`Field for ${column}`)

const choose = (column: string, value: string): void => {
  fireEvent.change(fieldOf(column), { target: { value } })
}

const shownColumns = (): string[] =>
  within(screen.getByRole('list', { name: 'Columns of the file' }))
    .getAllByRole('listitem')
    .map((item) => item.querySelector('strong, span')!.textContent)

const rowOf = (column: string): HTMLElement => fieldOf(column).closest<HTMLElement>('[role="listitem"]')!

afterEach(() => {
  cleanup()
})

describe('the mapping step', () => {
  it('maps the columns here; a configuration cannot be chosen yet', () => {
    renderStep()

    expect(screen.getByRole('radio', { name: 'Map the columns here' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.getByRole<HTMLButtonElement>('radio', { name: 'Use a Data Importer configuration' }).disabled).toBe(true)
  })

  it('shows a row per column with its first value', () => {
    renderStep()

    expect(shownColumns()).toEqual(['Artikelnr', 'Name DE', 'Preis', 'Notiz'])
    expect(within(rowOf('Preis')).getByText('12.50')).toBeTruthy()
    expect(within(rowOf('Notiz')).getByText('Größe M & L')).toBeTruthy()
  })

  it('marks a sure suggestion as automatic, with its language', () => {
    renderStep()

    expect(fieldOf('Name DE').value).toBe('name')
    expect(within(rowOf('Name DE')).getByText('auto')).toBeTruthy()
    expect(within(rowOf('Name DE')).getByText('de')).toBeTruthy()
  })

  it('names a weaker suggestion without taking it', () => {
    renderStep()

    expect(fieldOf('Preis').value).toBe('')
    expect(within(rowOf('Preis')).getByText('Suggested: Price')).toBeTruthy()
  })

  it('shows the match column with the field it finds the objects by, which cannot be changed', () => {
    renderStep()

    expect(fieldOf('Artikelnr').disabled).toBe(true)
    expect(within(rowOf('Artikelnr')).getByRole('option', { name: 'SKU' })).toBeTruthy()
    expect(within(rowOf('Artikelnr')).getByText('Finds the objects')).toBeTruthy()
  })

  it('shows the object ID for a match column when matching by it', () => {
    render(<Stateful initial={ createMapping(inspected, { ...objects, matchBy: 'id' }, loaded) } />)

    expect(within(rowOf('Artikelnr')).getByRole('option', { name: 'Object ID' })).toBeTruthy()
  })

  it('takes the auto mark away once the field is changed by hand', () => {
    renderStep()

    choose('Name DE', 'note')

    expect(fieldOf('Name DE').value).toBe('note')
    expect(within(rowOf('Name DE')).queryByText('auto')).toBeNull()
    expect(within(rowOf('Name DE')).queryByText('de')).toBeNull()
  })

  it('can leave a column without a field again', () => {
    renderStep()

    choose('Name DE', '')

    expect(fieldOf('Name DE').value).toBe('')
    expect(within(rowOf('Name DE')).queryByText('auto')).toBeNull()
  })

  it('offers a field another column goes into only to that column', () => {
    renderStep()
    choose('Notiz', 'price')

    expect(within(fieldOf('Preis')).getByRole<HTMLOptionElement>('option', { name: 'Price' }).disabled).toBe(true)
    expect(within(fieldOf('Notiz')).getByRole<HTMLOptionElement>('option', { name: 'Price' }).disabled).toBe(false)
    expect(within(fieldOf('Notiz')).getByRole<HTMLOptionElement>('option', { name: 'SKU' }).disabled).toBe(true)
  })

  it('names the fields the class requires, unescaped', () => {
    renderStep()

    expect(within(fieldOf('Notiz')).getByRole('option', { name: 'EAN (required)' })).toBeTruthy()
    expect(within(fieldOf('Notiz')).getByRole('option', { name: 'Note & remark' })).toBeTruthy()
  })

  it('warns of required fields no column goes into until one does', () => {
    renderStep()

    expect(screen.getByRole('alert').textContent).toBe('Required fields without a column: EAN')

    choose('Notiz', 'ean')

    expect(screen.queryByRole('alert')).toBeNull()
  })

  describe('a column not to import', () => {
    it('moves to the area of columns not imported, and back', () => {
      renderStep()

      choose('Name DE', '-')

      expect(shownColumns()).toEqual(['Artikelnr', 'Preis', 'Notiz'])
      const area = screen.getByRole('region', { name: 'Not imported (1)' })
      expect(within(area).getByText('Name DE')).toBeTruthy()

      fireEvent.click(within(area).getByRole('button', { name: 'Restore Name DE' }))

      expect(shownColumns()).toEqual(['Artikelnr', 'Name DE', 'Preis', 'Notiz'])
      expect(fieldOf('Name DE').value).toBe('')
      expect(screen.queryByRole('region', { name: /Not imported/ })).toBeNull()
    })

    it('frees its field for another column', () => {
      renderStep()
      choose('Notiz', 'price')

      choose('Notiz', '-')

      expect(within(fieldOf('Preis')).getByRole<HTMLOptionElement>('option', { name: 'Price' }).disabled).toBe(false)
      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Use all suggestions (1)' }).disabled).toBe(false)
    })
  })

  describe('using all suggestions', () => {
    it('takes every suggestion waiting, marked as automatic', () => {
      renderStep()

      fireEvent.click(screen.getByRole('button', { name: 'Use all suggestions (1)' }))

      expect(fieldOf('Preis').value).toBe('price')
      expect(within(rowOf('Preis')).getByText('auto')).toBeTruthy()
      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Use all suggestions (0)' }).disabled).toBe(true)
    })

    it('leaves a column whose suggested field another column took by hand', () => {
      renderStep()
      choose('Notiz', 'price')

      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Use all suggestions (0)' }).disabled).toBe(true)
    })
  })

  describe('filtering', () => {
    it('counts and shows the mapped and the open columns', () => {
      renderStep()

      expect(screen.getByRole('radio', { name: 'All (4)' })).toBeTruthy()
      fireEvent.click(screen.getByRole('radio', { name: 'Mapped (2)' }))
      expect(shownColumns()).toEqual(['Artikelnr', 'Name DE'])

      fireEvent.click(screen.getByRole('radio', { name: 'Open (2)' }))
      expect(shownColumns()).toEqual(['Preis', 'Notiz'])
    })

    it('finds a column by its name', () => {
      renderStep()

      fireEvent.change(screen.getByRole('searchbox', { name: 'Find a column' }), { target: { value: 'pre' } })

      expect(shownColumns()).toEqual(['Preis'])
    })

    it('says when no column is left to show', () => {
      renderStep()

      fireEvent.change(screen.getByRole('searchbox', { name: 'Find a column' }), { target: { value: 'missing' } })

      expect(screen.getByText('No column to show')).toBeTruthy()
    })
  })

  it('shows that it loads before there is a mapping', () => {
    render(
      <Stateful
        initial={ null }
        isLoading
      />
    )

    expect(document.querySelector('[aria-busy="true"]')).not.toBeNull()
    expect(screen.queryByRole('list', { name: 'Columns of the file' })).toBeNull()
  })
})
