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
let mockLanguages = ['en', 'de', 'fr']
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: mockLanguages }) }))
// opens only with a file to run the transformations on, which these tests do not give
jest.mock('./transformations-modal', () => ({ TransformationsModal: () => null }))
// the key dialog loads its keys from the server; here it offers one key to take, and a way out
jest.mock('./store-key-dialog', () => ({
  StoreKeyDialog: ({ fieldName, onSelect, onClose, takenKeyIds }: { fieldName: string, onSelect: (key: { id: string, label: string }) => void, onClose: () => void, takenKeyIds?: string[] }) => (
    <dialog
      aria-label={ `Keys of ${fieldName}` }
      open
    >
      <button
        disabled={ takenKeyIds?.includes('2-4') }
        onClick={ () => { onSelect({ id: '2-4', label: 'Dimensions › Width' }) } }
        type="button"
      >
        Width
      </button>
      <button
        onClick={ onClose }
        type="button"
      >
        Cancel
      </button>
    </dialog>
  )
}))

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
  ({ key, title, localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true, group: ['Product'], classificationStore: false, ...given })

const loaded: LoadedMapping = {
  fields: [
    field('sku', 'SKU'),
    field('name', 'Name', { localized: true }),
    field('price', 'Price', { group: ['Prices'] }),
    field('note', 'Note & remark'),
    field('ean', 'EAN', { mandatory: true }),
    field('technical.Engine.power', 'Power', { group: ['Prices', 'technical', 'Engine'] }),
    field('attributes', 'Attributes', { localized: true, classificationStore: true, group: ['Specification'] })
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
      classId="PROD"
      configuration={ { isLoading: false, onChoose: () => {} } }
      fields={ loaded.fields }
      isLoading={ isLoading }
      mapWith="here"
      mapping={ mapping }
      onChange={ setMapping }
      onMapWithChange={ () => {} }
      source={ null }
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
  mockLanguages = ['en', 'de', 'fr']
})

describe('picking fields', () => {
  describe('the field panel', () => {
    const panel = (): HTMLElement => screen.getByRole('region', { name: /^Fields/ })
    const fieldInPanel = (name: string): HTMLButtonElement => within(panel()).getByRole<HTMLButtonElement>('treeitem', { name })

    it('picks the field of the first column that can take one, grouped as in the class layout', () => {
      renderStep()

      expect(panel().getAttribute('aria-label')).toBe('Fields for Name DE')
      expect(rowOf('Name DE').getAttribute('aria-current')).toBe('true')
      expect(document.querySelectorAll('[aria-current="true"]')).toHaveLength(1)
      const groups = within(panel()).getAllByRole('group').map((group) => group.getAttribute('aria-label'))
      expect(groups).toEqual(['Product', 'Prices', 'technical', 'Engine', 'Specification'])
      expect(within(within(panel()).getByRole('group', { name: 'Engine' })).getByRole('treeitem', { name: 'Power' })).toBeTruthy()
      expect(fieldInPanel('EAN (required)')).toBeTruthy()
    })

    it('picks for the column clicked or focused, never for the match column', () => {
      renderStep()

      fireEvent.click(rowOf('Notiz'))
      expect(panel().getAttribute('aria-label')).toBe('Fields for Notiz')

      fireEvent.focus(fieldOf('Preis'))
      expect(panel().getAttribute('aria-label')).toBe('Fields for Preis')

      fireEvent.click(rowOf('Artikelnr'))
      expect(panel().getAttribute('aria-label')).toBe('Fields for Preis')
    })

    it('puts the field picked into the column, by hand', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))

      fireEvent.click(fieldInPanel('Note & remark'))

      expect(fieldOf('Notiz').value).toBe('note')
      expect(within(rowOf('Notiz')).queryByText('auto')).toBeNull()
    })

    it('offers a field another column goes into only to that column', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))
      fireEvent.click(fieldInPanel('Price'))

      expect(fieldInPanel('Price').disabled).toBe(false)
      fireEvent.click(rowOf('Preis'))
      expect(fieldInPanel('Price').disabled).toBe(true)
      expect(fieldInPanel('Note & remark').disabled).toBe(false)
    })

    it('moves to another column when the one it picked for is not imported', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))

      choose('Notiz', '-')

      expect(panel().getAttribute('aria-label')).toBe('Fields for Name DE')
    })

    it('asks for a column while none can take a field', () => {
      render(<Stateful initial={ createMapping({ ...inspected, columns: [inspected.columns[0]] }, objects, loaded) } />)

      expect(panel().getAttribute('aria-label')).toBe('Fields')
      expect(within(panel()).getByText('Pick a column to choose its field here')).toBeTruthy()
    })
  })

  describe('a localized field', () => {
    it('shows the language of the column, which can be changed', () => {
      renderStep()

      const language = within(rowOf('Name DE')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' })
      expect(language.value).toBe('de')

      fireEvent.change(language, { target: { value: 'fr' } })

      expect(within(rowOf('Name DE')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('fr')
      expect(fieldOf('Name DE').value).toBe('name')
    })

    it('offers no language another column has for the field', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))
      fireEvent.click(within(screen.getByRole('region', { name: 'Fields for Notiz' })).getByRole('treeitem', { name: 'Name' }))

      const languagesOf = (column: string): string[] =>
        Array.from(within(rowOf(column)).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).options).map(({ value }) => value)
      expect(languagesOf('Notiz')).toEqual(['en', 'fr'])
      expect(languagesOf('Name DE')).toEqual(['de', 'fr'])
    })

    it('picked by hand gets the first language no other column has', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))

      fireEvent.click(within(screen.getByRole('region', { name: 'Fields for Notiz' })).getByRole('treeitem', { name: 'Name' }))

      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')
    })
  })

  describe('a classification store', () => {
    it('goes in by a key chosen in its dialog, from the panel or the field list', () => {
      renderStep()
      fireEvent.click(rowOf('Notiz'))

      fireEvent.click(within(screen.getByRole('region', { name: 'Fields for Notiz' })).getByRole('treeitem', { name: 'Attributes' }))
      fireEvent.click(within(screen.getByRole('dialog', { name: 'Keys of attributes' })).getByRole('button', { name: 'Width' }))

      expect(screen.queryByRole('dialog')).toBeNull()
      expect(fieldOf('Notiz').value).toBe('attributes')
      expect(within(rowOf('Notiz')).getByRole('button', { name: 'Change the key for Notiz' }).textContent).toBe('Dimensions › Width')
      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')

      choose('Preis', 'attributes')
      expect(screen.getByRole('dialog', { name: 'Keys of attributes' })).toBeTruthy()
    })

    it('keeps the column as it was when its dialog is closed', () => {
      renderStep()

      choose('Preis', 'attributes')
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

      expect(screen.queryByRole('dialog')).toBeNull()
      expect(fieldOf('Preis').value).toBe('')
    })

    it('takes a key another column goes into only in another language', () => {
      renderStep()
      choose('Notiz', 'attributes')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))

      choose('Preis', 'attributes')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))

      const language = within(rowOf('Preis')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' })
      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')
      expect(language.value).toBe('de')
      expect(Array.from(language.options).map(({ value }) => value)).toEqual(['de', 'fr'])
    })

    it('does not offer a key other columns hold in every language', () => {
      mockLanguages = ['en']
      renderStep()
      choose('Notiz', 'attributes')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))

      choose('Preis', 'attributes')

      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Width' }).disabled).toBe(true)
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
      fireEvent.click(within(rowOf('Notiz')).getByRole('button', { name: 'Change the key for Notiz' }))
      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Width' }).disabled).toBe(false)
    })

    it('can change its key', () => {
      renderStep()
      choose('Preis', 'attributes')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))

      fireEvent.click(within(rowOf('Preis')).getByRole('button', { name: 'Change the key for Preis' }))

      expect(screen.getByRole('dialog', { name: 'Keys of attributes' })).toBeTruthy()
    })
  })
})
