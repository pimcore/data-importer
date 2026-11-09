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
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type InspectedFile, type ObjectsSettings } from '../file-step/file-step-state'
import { createMapping, type LoadedMapping, type Mapping } from './mapping-state'
import { chooseStoreKey } from './mapping-choices'
import { MappingStep } from './mapping-step'
import { type TransformationSource } from './transformations-editor'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  useSettings: () => ({ validLanguages: ['en', 'de', 'fr'] }),
  ApiError: class { constructor (readonly details: unknown) {} },
  trackError: jest.fn()
}))
// loads its keys from the server, which the field panel's test stands in for
jest.mock('./store-key-dialog', () => ({ StoreKeyDialog: () => null }))
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportPreviewTransformationsMutation: () => [mockPreview]
}))

const mockPreview = jest.fn()

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
    field('price', 'Price'),
    field('note', 'Note & remark'),
    field('ean', 'EAN', { mandatory: true }),
    field('image', 'Image', { transformationResultType: 'asset', takesColumnAsIs: false })
  ],
  suggestions: [
    { sourceIndex: 'Name DE', sourceLabel: 'Name DE', targetFieldName: 'name', targetFieldLabel: 'Name', transformationResultType: 'default', score: 100, language: 'de' },
    { sourceIndex: 'Preis', sourceLabel: 'Preis', targetFieldName: 'price', targetFieldLabel: 'Price', transformationResultType: 'default', score: 55, language: null }
  ]
}

const source: TransformationSource = { fileId: 'file-1', classId: 'PROD', settings: { hasHeaderRow: true } }

const Stateful = ({ initial, isLoading = false, withSource = source }: { initial: Mapping | null, isLoading?: boolean, withSource?: TransformationSource | null }): React.JSX.Element => {
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
      source={ withSource }
    />
  )
}

const renderStep = (): void => {
  render(<Stateful initial={ createMapping(inspected, objects, loaded) } />)
}

const rowOf = (column: string): HTMLElement => screen.queryByRole('button', { name: `Field for ${column}` })?.closest<HTMLElement>('[role="listitem"]') ?? matchRowOf(column)
const matchRowOf = (column: string): HTMLElement => within(screen.getByRole('list', { name: 'Columns of the file' })).getByText(column).closest<HTMLElement>('[role="listitem"]')!
const panelOf = (column: string): HTMLElement => screen.getByRole('complementary', { name: `Column ${column}` })

const openPanel = (column: string): void => {
  fireEvent.click(screen.getByRole('button', { name: `Field for ${column}` }))
}

const closePanel = (column: string): void => {
  fireEvent.click(within(panelOf(column)).getByRole('button', { name: 'Close' }))
}

const choose = (column: string, field: string): void => {
  openPanel(column)
  fireEvent.click(within(panelOf(column)).getByRole('treeitem', { name: field }))
  closePanel(column)
}

const skip = (column: string): void => {
  fireEvent.click(screen.getByRole('button', { name: `Don't import ${column}` }))
}

const shownColumns = (): string[] =>
  within(screen.getByRole('list', { name: 'Columns of the file' }))
    .getAllByRole('listitem')
    .map((item) => Array.from(item.querySelectorAll('strong, span')).find((part) => part.textContent !== '')!.textContent)

afterEach(() => {
  cleanup()
})

describe('the mapping step', () => {
  it('maps the columns here, and a configuration can be chosen instead', () => {
    renderStep()

    expect(screen.getByRole('radio', { name: 'Map the columns here' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.getByRole<HTMLButtonElement>('radio', { name: 'Use a Data Importer configuration' }).disabled).toBe(false)
  })

  it('shows a row per column with its first value', () => {
    renderStep()

    expect(shownColumns()).toEqual(['Artikelnr', 'Name DE', 'Preis', 'Notiz'])
    expect(within(rowOf('Preis')).getByText('e.g. “12.50”')).toBeTruthy()
    expect(within(rowOf('Notiz')).getByText('e.g. “Größe M & L”')).toBeTruthy()
  })

  it('marks a sure suggestion as automatic, with its language', () => {
    renderStep()

    expect(within(rowOf('Name DE')).getByRole('button', { name: 'Field for Name DE' }).textContent).toContain('Name')
    expect(within(rowOf('Name DE')).getByText('auto')).toBeTruthy()
    expect(within(rowOf('Name DE')).getByText('de')).toBeTruthy()
  })

  it('offers a weaker suggestion to take at once', () => {
    renderStep()

    fireEvent.click(within(rowOf('Preis')).getByRole('button', { name: 'Use Price?' }))

    expect(within(rowOf('Preis')).getByRole('button', { name: 'Field for Preis' }).textContent).toContain('Price')
    expect(within(rowOf('Preis')).queryByRole('button', { name: 'Use Price?' })).toBeNull()
  })

  it('shows the match column with the field it finds the objects by, which cannot be changed', () => {
    renderStep()

    expect(screen.queryByRole('button', { name: 'Field for Artikelnr' })).toBeNull()
    expect(within(matchRowOf('Artikelnr')).getByText('SKU')).toBeTruthy()
    expect(within(matchRowOf('Artikelnr')).getByText('Finds the objects')).toBeTruthy()
    expect(screen.queryByRole('button', { name: "Don't import Artikelnr" })).toBeNull()
  })

  it('shows the object ID for a match column when matching by it', () => {
    render(<Stateful initial={ createMapping(inspected, { ...objects, matchBy: 'id' }, loaded) } />)

    expect(within(matchRowOf('Artikelnr')).getByText('Object ID')).toBeTruthy()
  })

  it('takes the auto mark away once the field is changed by hand', () => {
    renderStep()

    choose('Name DE', 'Note & remark')

    expect(within(rowOf('Name DE')).getByRole('button', { name: 'Field for Name DE' }).textContent).toContain('Note & remark')
    expect(within(rowOf('Name DE')).queryByText('auto')).toBeNull()
    expect(within(rowOf('Name DE')).queryByText('de')).toBeNull()
  })

  it('can leave a column without a field again', () => {
    renderStep()

    openPanel('Name DE')
    fireEvent.click(within(panelOf('Name DE')).getByRole('button', { name: 'Remove field' }))

    expect(within(rowOf('Name DE')).getByRole('button', { name: 'Field for Name DE' }).textContent).toBe('Choose field')
    expect(within(rowOf('Name DE')).queryByText('auto')).toBeNull()
  })

  it('offers a field another column goes into only to that column', () => {
    renderStep()
    choose('Notiz', 'Price')

    openPanel('Preis')
    expect(within(panelOf('Preis')).getByRole<HTMLButtonElement>('treeitem', { name: 'Price' }).disabled).toBe(true)
    closePanel('Preis')
    openPanel('Notiz')
    expect(within(panelOf('Notiz')).getByRole<HTMLButtonElement>('treeitem', { name: 'Price' }).disabled).toBe(false)
  })

  it('names the fields the class requires, unescaped', () => {
    renderStep()
    openPanel('Notiz')

    expect(within(panelOf('Notiz')).getByRole('treeitem', { name: 'EAN (required)' })).toBeTruthy()
    expect(within(panelOf('Notiz')).getByRole('treeitem', { name: 'Note & remark' })).toBeTruthy()
  })

  it('shows whether each required field has a column', () => {
    renderStep()

    expect(screen.getByText('Required fields:')).toBeTruthy()
    expect(screen.getByText('✗ EAN')).toBeTruthy()

    choose('Notiz', 'EAN (required)')

    expect(screen.getByText('✓ EAN')).toBeTruthy()
    expect(screen.queryByText('✗ EAN')).toBeNull()
  })

  it('says how many columns go into a field and what was mapped automatically', () => {
    renderStep()

    expect(screen.getByText('2 of 4 columns go into a field')).toBeTruthy()
    expect(screen.getByText('1 column was mapped automatically')).toBeTruthy()
    expect(screen.getByText('when the file was read, marked “auto”. 1 more has a suggestion to confirm.')).toBeTruthy()
  })

  describe('a column not to import', () => {
    it('moves to the area of columns not imported, and back', () => {
      renderStep()

      skip('Name DE')

      expect(shownColumns()).toEqual(['Artikelnr', 'Preis', 'Notiz'])
      fireEvent.click(screen.getByRole('button', { name: /Not imported \(1\)/ }))
      fireEvent.click(screen.getByRole('button', { name: 'Restore Name DE' }))

      expect(shownColumns()).toEqual(['Artikelnr', 'Name DE', 'Preis', 'Notiz'])
      expect(within(rowOf('Name DE')).getByRole('button', { name: 'Field for Name DE' }).textContent).toBe('Choose field')
      expect(screen.queryByRole('button', { name: /Not imported/ })).toBeNull()
    })

    it('frees its field for another column', () => {
      renderStep()
      choose('Notiz', 'Price')

      skip('Notiz')

      openPanel('Preis')
      expect(within(panelOf('Preis')).getByRole<HTMLButtonElement>('treeitem', { name: 'Price' }).disabled).toBe(false)
    })

    it('takes every open column at once, leaving the decided ones', () => {
      renderStep()

      fireEvent.click(screen.getByRole('button', { name: "Don't import the 2 not mapped" }))

      expect(shownColumns()).toEqual(['Artikelnr', 'Name DE'])
      expect(screen.getByRole('button', { name: /Not imported \(2\)/ })).toBeTruthy()
      expect(screen.queryByRole('button', { name: /not mapped$/ })).toBeNull()
    })

    it('finds a column in the area by its name', () => {
      renderStep()
      skip('Name DE')
      skip('Preis')
      fireEvent.click(screen.getByRole('button', { name: /Not imported \(2\)/ }))

      fireEvent.change(screen.getByRole('textbox', { name: 'Search columns' }), { target: { value: 'pre' } })

      expect(screen.queryByRole('button', { name: 'Restore Name DE' })).toBeNull()
      expect(screen.getByRole('button', { name: 'Restore Preis' })).toBeTruthy()
    })
  })

  describe('using all suggestions', () => {
    it('takes every suggestion waiting, marked as automatic', () => {
      renderStep()

      fireEvent.click(screen.getByRole('button', { name: 'Use all 1 suggestions' }))

      expect(within(rowOf('Preis')).getByRole('button', { name: 'Field for Preis' }).textContent).toContain('Price')
      expect(within(rowOf('Preis')).getByText('auto')).toBeTruthy()
      expect(screen.queryByRole('button', { name: /^Use all/ })).toBeNull()
    })

    it('leaves a column whose suggested field another column took by hand', () => {
      renderStep()
      choose('Notiz', 'Price')

      expect(screen.queryByRole('button', { name: /^Use all/ })).toBeNull()
    })
  })

  describe('filtering', () => {
    it('counts and shows the mapped and the open columns', () => {
      renderStep()

      expect(screen.getByRole('button', { name: 'To import 4' })).toBeTruthy()
      fireEvent.click(screen.getByRole('button', { name: 'Mapped 2' }))
      expect(shownColumns()).toEqual(['Artikelnr', 'Name DE'])

      fireEvent.click(screen.getByRole('button', { name: 'Not mapped 2' }))
      expect(shownColumns()).toEqual(['Preis', 'Notiz'])
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
  describe('with transformations', () => {
    const answer = (fits: boolean): unknown => ({
      data: { values: [], steps: [], conversion: null, resultType: 'default', fits, results: [], offered: ['loadAsset'] }
    })

    const settle = async (): Promise<void> => {
      await act(async () => {
        jest.advanceTimersByTime(300)
        await Promise.resolve()
        await Promise.resolve()
      })
    }

    beforeEach(() => {
      jest.useFakeTimers()
      mockPreview.mockReset()
    })

    afterEach(() => {
      jest.useRealTimers()
    })

    const transformationsOf = (column: string): HTMLElement => within(rowOf(column)).getByRole('button', { name: `Transformations of ${column}` })

    it('opens the transformations of a column with a field and applies them to it as they are made', async () => {
      mockPreview.mockResolvedValue(answer(true))
      renderStep()

      expect(within(rowOf('Notiz')).queryByRole('button', { name: 'Transformations of Notiz' })).toBeNull()
      choose('Notiz', 'Note & remark')
      await settle()
      fireEvent.click(transformationsOf('Notiz'))
      await settle()

      fireEvent.click(within(panelOf('Notiz')).getByRole('button', { name: '+ Load asset' }))
      await settle()
      closePanel('Notiz')

      expect(screen.queryByRole('complementary', { name: 'Column Notiz' })).toBeNull()
      expect(transformationsOf('Notiz').textContent).toBe('1 transformation')
    })

    it('marks a column whose field does not take it until its transformations fit', async () => {
      mockPreview.mockResolvedValue(answer(true))
      renderStep()

      openPanel('Notiz')
      fireEvent.click(within(panelOf('Notiz')).getByRole('treeitem', { name: 'Image' }))

      expect(within(rowOf('Notiz')).getByText('Needs a transformation')).toBeTruthy()
      expect(screen.getByText('Columns whose values do not fit their field yet: Notiz')).toBeTruthy()

      await settle()

      expect(within(rowOf('Notiz')).queryByText('Needs a transformation')).toBeNull()
      expect(screen.queryByText('Columns whose values do not fit their field yet: Notiz')).toBeNull()
    })

    it('offers none for the match column, or without a file to run them on', () => {
      renderStep()
      expect(within(rowOf('Artikelnr')).queryByRole('button', { name: 'Transformations of Artikelnr' })).toBeNull()
      cleanup()

      render(<Stateful
        initial={ createMapping(inspected, objects, loaded) }
        withSource={ null }
             />)
      expect(within(rowOf('Name DE')).queryByRole('button', { name: 'Transformations of Name DE' })).toBeNull()
    })

    it('offers none for a column going into a classification store key', () => {
      const store = field('attributes', 'Attributes', { classificationStore: true })
      const mapping = createMapping(inspected, objects, { ...loaded, fields: [...loaded.fields, store] })
      render(<Stateful initial={ chooseStoreKey(mapping, 'Notiz', 'attributes', { id: '2-4', label: 'Dimensions › Width' }) } />)

      expect(within(rowOf('Notiz')).getByRole('button', { name: 'Change the key for Notiz' })).toBeTruthy()
      expect(within(rowOf('Notiz')).queryByRole('button', { name: 'Transformations of Notiz' })).toBeNull()
    })
  })
})
