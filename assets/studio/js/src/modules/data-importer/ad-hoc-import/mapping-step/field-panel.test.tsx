/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */
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
// runs only with a file to run the transformations on, which these tests do not give
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportPreviewTransformationsMutation: () => [jest.fn()]
}))
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

const rowOf = (column: string): HTMLElement => screen.getByRole('button', { name: `Field for ${column}` }).closest<HTMLElement>('li')!
const panelOf = (column: string): HTMLElement => screen.getByRole('complementary', { name: `Column ${column}` })
const fieldButton = (column: string): HTMLElement => screen.getByRole('button', { name: `Field for ${column}` })

const openPanel = (column: string): void => {
  fireEvent.click(fieldButton(column))
}

const closePanel = (column: string): void => {
  fireEvent.click(within(panelOf(column)).getByRole('button', { name: 'Close' }))
}

const pickIn = (column: string, name: string): void => {
  fireEvent.click(within(panelOf(column)).getByRole('treeitem', { name }))
}

afterEach(() => {
  cleanup()
  mockLanguages = ['en', 'de', 'fr']
})

describe('picking fields', () => {
  describe('the side panel', () => {
    it('opens on the column, with its fields grouped as in the class layout', () => {
      renderStep()

      openPanel('Name DE')

      expect(rowOf('Name DE').getAttribute('aria-current')).toBe('true')
      expect(document.querySelectorAll('[aria-current="true"]')).toHaveLength(1)
      const groups = within(panelOf('Name DE')).getAllByRole('group').map((group) => group.getAttribute('aria-label'))
      expect(groups).toEqual(['Product', 'Prices', 'technical', 'Engine', 'Specification'])
      expect(within(within(panelOf('Name DE')).getByRole('group', { name: 'Engine' })).getByRole('treeitem', { name: 'Power' })).toBeTruthy()
      expect(within(panelOf('Name DE')).getByRole('treeitem', { name: 'EAN (required)' })).toBeTruthy()
    })

    it('moves to the next and the previous column, passing the match column', () => {
      renderStep()
      openPanel('Name DE')

      fireEvent.click(within(panelOf('Name DE')).getByRole('button', { name: 'Next column ›' }))
      expect(panelOf('Preis')).toBeTruthy()

      fireEvent.click(within(panelOf('Preis')).getByRole('button', { name: 'Previous column' }))
      fireEvent.click(within(panelOf('Name DE')).getByRole('button', { name: 'Previous column' }))
      expect(panelOf('Notiz')).toBeTruthy()
    })

    it('puts the field picked into the column, by hand', () => {
      renderStep()
      openPanel('Notiz')

      pickIn('Notiz', 'Note & remark')

      expect(fieldButton('Notiz').textContent).toContain('Note & remark')
      expect(within(rowOf('Notiz')).queryByText('auto')).toBeNull()
    })

    it('offers a field another column goes into only to that column', () => {
      renderStep()
      openPanel('Notiz')
      pickIn('Notiz', 'Price')

      expect(within(panelOf('Notiz')).getByRole<HTMLButtonElement>('treeitem', { name: 'Price' }).disabled).toBe(false)
      closePanel('Notiz')
      openPanel('Preis')
      expect(within(panelOf('Preis')).getByRole<HTMLButtonElement>('treeitem', { name: 'Price' }).disabled).toBe(true)
      expect(within(panelOf('Preis')).getByRole<HTMLButtonElement>('treeitem', { name: 'Note & remark' }).disabled).toBe(false)
    })

    it('shows a field of an object brick with its path', () => {
      renderStep()
      openPanel('Notiz')

      pickIn('Notiz', 'Power')

      expect(fieldButton('Notiz').textContent).toContain('Prices › technical › Engine')
    })

    it('closes when the column it is open on is not imported', () => {
      renderStep()
      openPanel('Notiz')

      fireEvent.click(within(panelOf('Notiz')).getByRole('button', { name: "Don't import" }))

      expect(screen.queryByRole('complementary')).toBeNull()
      expect(screen.queryByRole('button', { name: 'Field for Notiz' })).toBeNull()
    })
  })

  describe('a localized field', () => {
    it('shows the language of the column, which can be changed', () => {
      renderStep()

      const language = within(rowOf('Name DE')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' })
      expect(language.value).toBe('de')

      fireEvent.change(language, { target: { value: 'fr' } })

      expect(within(rowOf('Name DE')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('fr')
      expect(fieldButton('Name DE').textContent).toContain('Name')
    })

    it('offers no language another column has for the field', () => {
      renderStep()
      openPanel('Notiz')
      pickIn('Notiz', 'Name')
      closePanel('Notiz')

      const languagesOf = (column: string): string[] =>
        Array.from(within(rowOf(column)).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).options).map(({ value }) => value)
      expect(languagesOf('Notiz')).toEqual(['en', 'fr'])
      expect(languagesOf('Name DE')).toEqual(['de', 'fr'])
    })

    it('picked by hand gets the first language no other column has', () => {
      renderStep()
      openPanel('Notiz')

      pickIn('Notiz', 'Name')

      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')
    })
  })

  describe('a classification store', () => {
    const chooseStore = (column: string): void => {
      openPanel(column)
      pickIn(column, 'Attributes')
    }

    it('goes in by a key chosen in its dialog, from the panel', () => {
      renderStep()
      openPanel('Notiz')

      pickIn('Notiz', 'Attributes')
      fireEvent.click(within(screen.getByRole('dialog', { name: 'Keys of attributes' })).getByRole('button', { name: 'Width' }))

      expect(screen.queryByRole('dialog')).toBeNull()
      expect(fieldButton('Notiz').textContent).toContain('Attributes')
      expect(within(rowOf('Notiz')).getByRole('button', { name: 'Change the key for Notiz' }).textContent).toBe('Dimensions › Width')
      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')
    })

    it('keeps the column as it was when its dialog is closed', () => {
      renderStep()

      chooseStore('Preis')
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

      expect(screen.queryByRole('dialog')).toBeNull()
      expect(fieldButton('Preis').textContent).toBe('Choose field')
    })

    it('takes a key another column goes into only in another language', () => {
      renderStep()
      chooseStore('Notiz')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))
      closePanel('Notiz')

      chooseStore('Preis')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))

      const language = within(rowOf('Preis')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' })
      expect(within(rowOf('Notiz')).getByRole<HTMLSelectElement>('combobox', { name: 'Language' }).value).toBe('en')
      expect(language.value).toBe('de')
      expect(Array.from(language.options).map(({ value }) => value)).toEqual(['de', 'fr'])
    })

    it('does not offer a key other columns hold in every language', () => {
      mockLanguages = ['en']
      renderStep()
      chooseStore('Notiz')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))
      closePanel('Notiz')

      chooseStore('Preis')

      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Width' }).disabled).toBe(true)
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
      closePanel('Preis')
      fireEvent.click(within(rowOf('Notiz')).getByRole('button', { name: 'Change the key for Notiz' }))
      expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Width' }).disabled).toBe(false)
    })

    it('can change its key', () => {
      renderStep()
      chooseStore('Preis')
      fireEvent.click(screen.getByRole('button', { name: 'Width' }))
      closePanel('Preis')

      fireEvent.click(within(rowOf('Preis')).getByRole('button', { name: 'Change the key for Preis' }))

      expect(screen.getByRole('dialog', { name: 'Keys of attributes' })).toBeTruthy()
    })
  })
})
