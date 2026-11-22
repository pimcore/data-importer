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
import { type ImportConfiguration, type MapWith } from './configuration-state'
import { ConfigurationChoice } from './configuration-choice'
import { createMapping, type LoadedMapping } from './mapping-state'
import { MappingStep } from './mapping-step'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
// the mapping step loads the transformations modal and the store key dialog, which no test here opens
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: ['en'] }) }))
jest.mock('./store-key-dialog', () => ({ StoreKeyDialog: () => null }))
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportPreviewTransformationsMutation: () => [async () => await new Promise(() => {})]
}))

const inspected: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 1,
  columns: [
    { dataIndex: 'Artikelnr', label: 'Artikelnr', position: 0 },
    { dataIndex: 'Name DE', label: 'Name DE', position: 1 },
    { dataIndex: 'Preis', label: 'Preis', position: 2 }
  ],
  firstRows: [['NH-1', 'Jacke', '12.50']]
}

const objects: ObjectsSettings = { classId: 'PROD', matchBy: 'field', matchField: 'sku', matchColumn: 'Artikelnr', newObjectsFolder: null }

const loaded: LoadedMapping = {
  fields: [{ key: 'price', title: 'Price', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true, group: [], classificationStore: false }],
  suggestions: []
}

const configuration = (given: Partial<ImportConfiguration> = {}): ImportConfiguration => ({
  name: 'products',
  classId: 'PROD',
  className: 'Product',
  matchBy: 'field',
  matchField: 'name',
  matchLanguage: 'de',
  matchColumn: 'Name DE',
  newObjectsFolder: '/Products & more/Imported',
  columns: ['Name DE', 'Preis'],
  columnsBy: 'name',
  unusable: null,
  format: 'csv',
  sheet: null,
  headerRow: true,
  source: 'upload',
  sourcePath: null,
  fieldCount: 2,
  transformedCount: 0,
  schedule: null,
  ...given
})

interface StatefulProps {
  configurations?: ImportConfiguration[]
  isLoading?: boolean
}

const Stateful = ({ configurations = [], isLoading = false }: StatefulProps): React.JSX.Element => {
  const [mapping, setMapping] = useState(createMapping(inspected, objects, loaded))
  const [mapWith, setMapWith] = useState<MapWith>('here')
  const [chosen, setChosen] = useState<string | undefined>(undefined)

  return (
    <MappingStep
      classId="PROD"
      configuration={ { inspected, configurations, isLoading, chosen, onChoose: setChosen } }
      fields={ loaded.fields }
      isLoading={ false }
      mapWith={ mapWith }
      mapping={ mapping }
      onChange={ setMapping }
      onMapWithChange={ setMapWith }
      source={ null }
    />
  )
}

const useConfiguration = (props: StatefulProps = {}): void => {
  render(<Stateful { ...props } />)
  fireEvent.click(screen.getByRole('radio', { name: 'Use a Data Importer configuration' }))
}

const configurationList = (): HTMLElement => screen.getByRole('radiogroup', { name: 'Data Importer configurations' })

const cards = (): HTMLElement[] => within(configurationList()).getAllByRole('radio')

const card = (name: string): HTMLElement => within(configurationList()).getByRole('radio', { name })

const choose = (name: string): void => { fireEvent.click(card(name)) }

const detailOf = (shown: HTMLElement): string | null | undefined => document.getElementById(shown.getAttribute('aria-describedby') ?? '')?.textContent

afterEach(() => {
  cleanup()
})

describe('the mapping with a configuration', () => {
  it('offers the configurations instead of the columns', () => {
    useConfiguration({ configurations: [configuration(), configuration({ name: 'prices' })] })

    expect(screen.getByRole('radio', { name: 'Use a Data Importer configuration' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.queryByRole('list', { name: 'Columns of the file' })).toBeNull()
    expect(cards().map((shown) => shown.getAttribute('aria-label'))).toEqual(['products', 'prices'])
    expect(cards().map(detailOf)).toEqual(['CSV · 2 fields · manual', 'CSV · 2 fields · manual'])
    expect(screen.getByText('Only those you may read in the Data Hub')).toBeTruthy()
    expect(screen.getByText('Choose a configuration')).toBeTruthy()
  })

  it('names when a usable one runs: its cron definition, unescaped, or manual', () => {
    useConfiguration({ configurations: [configuration({ name: 'nightly', schedule: '0 2 * * 1-5 & more' }), configuration({ name: 'by hand' })] })

    expect(cards().map(detailOf)).toEqual(['CSV · 2 fields · 0 2 * * 1-5 & more', 'CSV · 2 fields · manual'])
  })

  it('lists the ones it cannot use after the usable ones, each part in the server\'s order', () => {
    useConfiguration({
      configurations: [
        configuration({ name: 'car-import', unusable: 'matching' }),
        configuration({ name: 'products' }),
        configuration({ name: 'closed', unusable: 'folder-not-allowed' }),
        configuration({ name: 'prices' })
      ]
    })

    expect(cards().map((shown) => shown.getAttribute('aria-label'))).toEqual(['products', 'prices', 'car-import', 'closed'])
    expect(cards().map((shown) => shown.getAttribute('aria-disabled'))).toEqual(['false', 'false', 'true', 'true'])
  })

  it('offers one it cannot use only with the reason on its second line, unescaped', () => {
    useConfiguration({
      configurations: [
        configuration({ name: 'Paths & more', unusable: 'matching' }),
        configuration({ name: 'elsewhere', matchColumn: 'EAN' }),
        configuration({ name: 'closed', unusable: 'folder-not-allowed' })
      ]
    })

    expect(cards().map(detailOf)).toEqual([
      'Product · it does not find objects by their ID or a field in all folders',
      'Product · the file has no column EAN to match by',
      'Product · you may not create objects in its folder'
    ])
    expect(cards().every((shown) => shown.getAttribute('aria-disabled') === 'true')).toBe(true)
    expect(cards().map(({ tabIndex }) => tabIndex)).toEqual([-1, -1, -1])
  })

  it('does not choose one it cannot use', () => {
    useConfiguration({ configurations: [configuration({ name: 'closed', unusable: 'folder-not-allowed' })] })
    choose('closed')

    expect(card('closed').getAttribute('aria-checked')).toBe('false')
    expect(screen.queryByRole('region', { name: 'closed' })).toBeNull()
  })

  it('marks the chosen one and can be chosen by keyboard', () => {
    useConfiguration({ configurations: [configuration(), configuration({ name: 'prices' })] })
    choose('products')

    expect(cards().map((shown) => shown.getAttribute('aria-checked'))).toEqual(['true', 'false'])
    expect(screen.queryByText('Choose a configuration')).toBeNull()

    fireEvent.keyDown(card('prices'), { key: 'Enter' })
    expect(cards().map((shown) => shown.getAttribute('aria-checked'))).toEqual(['false', 'true'])

    fireEvent.keyDown(card('products'), { key: ' ' })
    expect(card('products').getAttribute('aria-checked')).toBe('true')

    fireEvent.keyDown(card('prices'), { key: 'a' })
    expect(card('products').getAttribute('aria-checked')).toBe('true')
  })

  it('is one tab stop, the chosen card or else the first usable one', () => {
    useConfiguration({
      configurations: [
        configuration({ name: 'closed', unusable: 'folder-not-allowed' }),
        configuration(),
        configuration({ name: 'prices' })
      ]
    })

    // the one it cannot use is listed last
    expect(cards().map(({ tabIndex }) => tabIndex)).toEqual([0, -1, -1])
    choose('prices')
    expect(cards().map(({ tabIndex }) => tabIndex)).toEqual([-1, 0, -1])
  })

  it('moves to and chooses the next or previous usable one with the arrow keys, wrapping around', () => {
    useConfiguration({
      configurations: [
        configuration(),
        configuration({ name: 'closed', unusable: 'folder-not-allowed' }),
        configuration({ name: 'prices' })
      ]
    })
    // listed as products, prices, closed: the one it cannot use last
    const checked = (): Array<string | null> => cards().map((shown) => shown.getAttribute('aria-checked'))

    fireEvent.keyDown(card('products'), { key: 'ArrowDown' })
    expect(checked()).toEqual(['false', 'true', 'false'])
    expect(document.activeElement).toBe(card('prices'))

    fireEvent.keyDown(card('prices'), { key: 'ArrowRight' })
    expect(checked()).toEqual(['true', 'false', 'false'])
    expect(document.activeElement).toBe(card('products'))

    fireEvent.keyDown(card('products'), { key: 'ArrowUp' })
    expect(checked()).toEqual(['false', 'true', 'false'])

    fireEvent.keyDown(card('prices'), { key: 'ArrowLeft' })
    expect(checked()).toEqual(['true', 'false', 'false'])
    expect(document.activeElement).toBe(card('products'))
  })

  it('chooses nothing with the arrow keys when no configuration is usable', () => {
    useConfiguration({
      configurations: [
        configuration({ name: 'closed', unusable: 'folder-not-allowed' }),
        configuration({ name: 'locked', unusable: 'folder-not-allowed' })
      ]
    })

    fireEvent.keyDown(card('closed'), { key: 'ArrowDown' })
    fireEvent.keyDown(card('locked'), { key: 'ArrowUp' })

    expect(cards().map((shown) => shown.getAttribute('aria-checked'))).toEqual(['false', 'false'])
    expect(screen.queryAllByRole('region')).toHaveLength(0)
  })

  it('shows the class, the match and the folder of the chosen one, which replace those of the file step', () => {
    useConfiguration({ configurations: [configuration()] })
    choose('products')

    const summary = within(screen.getByRole('region', { name: 'products' }))
    expect(summary.getByText('Product')).toBeTruthy()
    expect(summary.getByText('name (de) ← Name DE')).toBeTruthy()
    expect(summary.getByText('/Products & more/Imported')).toBeTruthy()
    expect(summary.getByText('read only')).toBeTruthy()
    expect(screen.queryByText(/the configuration reads/)).toBeNull()
  })

  it('names the match of one without a language, and of one by the object ID', () => {
    useConfiguration({
      configurations: [
        configuration({ matchLanguage: null }),
        configuration({ name: 'by-id', matchBy: 'id', matchField: null, matchLanguage: null, matchColumn: 'Artikelnr', className: null })
      ]
    })
    choose('products')
    expect(screen.getByText('name ← Name DE')).toBeTruthy()

    choose('by-id')
    const summary = within(screen.getByRole('region', { name: 'by-id' }))
    expect(summary.getByText('Object ID ← Artikelnr')).toBeTruthy()
    expect(summary.getByText('PROD')).toBeTruthy()
  })

  it('says that one matching nothing creates every row, keyed by the file step\'s column', () => {
    useConfiguration({
      configurations: [configuration({ name: 'new-only', matchBy: 'none', matchField: null, matchLanguage: null, matchColumn: null })]
    })
    choose('new-only')

    const summary = within(screen.getByRole('region', { name: 'new-only' }))
    expect(summary.getByText('Every row creates a new object, keyed by the file step\'s column')).toBeTruthy()
  })

  it('warns of the columns it reads that the file does not have, unescaped', () => {
    useConfiguration({ configurations: [configuration({ columns: ['Name DE', 'EAN', 'Weight & size'] })] })
    choose('products')

    expect(screen.getByText('2 columns the configuration reads are missing in this file: “EAN”, “Weight & size”. Those fields stay as they are.')).toBeTruthy()
  })

  it('finds the columns of one that names them by position in a file with a header row', () => {
    useConfiguration({ configurations: [configuration({ columnsBy: 'position', matchColumn: '1', columns: ['1', '2', '7'] })] })
    choose('products')

    expect(screen.getByText('name (de) ← Name DE')).toBeTruthy()
    expect(screen.getByText('1 column the configuration reads is missing in this file: “7”. That field stays as it is.')).toBeTruthy()
  })

  it('shows the source it reads instead of this upload, its file format and its mapping', () => {
    render(
      <ConfigurationChoice
        chosen="northhike"
        configurations={ [configuration({
          name: 'northhike',
          format: 'xlsx',
          sheet: 'Products & more',
          source: 'sftp',
          sourcePath: '/in/northhike',
          fieldCount: 18,
          transformedCount: 6
        })] }
        fileName="autumn & winter.csv"
        inspected={ inspected }
        isLoading={ false }
        onChoose={ () => {} }
      />
    )

    const summary = screen.getByRole('region', { name: 'northhike' })
    expect(summary.querySelector('s')?.textContent).toBe('SFTP /in/northhike')
    expect(summary.textContent).toContain('SFTP /in/northhike → this upload: autumn & winter.csv')
    expect(within(summary).getByText(/^XLSX, sheet “Products & more”, header row 1/)).toBeTruthy()
    // the file is a CSV, so the format does not match it
    expect(within(summary).queryByText('matches the file')).toBeNull()
    expect(within(summary).getByText('18 fields, 6 with transformations')).toBeTruthy()
    expect(detailOf(card('northhike'))).toBe('XLSX · 18 fields · manual')
  })

  it('says the format matches the file, and names an upload without a path and one field', () => {
    render(
      <ConfigurationChoice
        chosen="products"
        configurations={ [configuration({ headerRow: false, fieldCount: 1, transformedCount: 0 })] }
        fileName="products.csv"
        inspected={ inspected }
        isLoading={ false }
        onChoose={ () => {} }
      />
    )

    const summary = within(screen.getByRole('region', { name: 'products' }))
    expect(summary.getByText('Upload')).toBeTruthy()
    expect(summary.getByText('matches the file')).toBeTruthy()
    expect(summary.getByText('1 field, 0 with transformations')).toBeTruthy()
    expect(summary.queryByText(/header row/)).toBeNull()
  })

  it('names a source another bundle loads from by its type', () => {
    render(
      <ConfigurationChoice
        chosen="products"
        configurations={ [configuration({ source: 'ftp', sourcePath: null })] }
        fileName="products.csv"
        inspected={ inspected }
        isLoading={ false }
        onChoose={ () => {} }
      />
    )

    const summary = screen.getByRole('region', { name: 'products' })
    expect(summary.querySelector('s')?.textContent).toBe('ftp')
    expect(summary.textContent).not.toContain('data-importer.ad-hoc-import.configuration.source.')
  })

  it('names only this upload for a configuration whose source is unknown', () => {
    render(
      <ConfigurationChoice
        chosen="products"
        configurations={ [configuration({ source: null, format: null })] }
        fileName="products.csv"
        inspected={ inspected }
        isLoading={ false }
        onChoose={ () => {} }
      />
    )

    const summary = screen.getByRole('region', { name: 'products' })
    expect(summary.querySelector('s')).toBeNull()
    expect(within(summary).getByText('this upload: products.csv')).toBeTruthy()
    expect(within(summary).queryByText('matches the file')).toBeNull()
    expect(detailOf(card('products'))).toBe('2 fields · manual')
  })

  it('shows a failed listing instead of saying there is none', () => {
    render(
      <ConfigurationChoice
        inspected={ inspected }
        isError
        isLoading={ false }
        onChoose={ () => {} }
      />
    )

    expect(screen.getByText('The configurations could not be loaded.')).toBeTruthy()
    expect(screen.queryByText('There is no data object import configuration you may use.')).toBeNull()
  })

  it('shows that the configurations load', () => {
    useConfiguration({ isLoading: true })

    expect(document.querySelector('[aria-busy="true"]')).not.toBeNull()
    expect(screen.queryByRole('radiogroup', { name: 'Data Importer configurations' })).toBeNull()
  })

  it('says when there is none to use', () => {
    useConfiguration()

    expect(screen.getByText('There is no data object import configuration you may use.')).toBeTruthy()
  })

  it('keeps the mapping made here for a switch back', () => {
    render(<Stateful />)
    fireEvent.click(screen.getByRole('button', { name: 'Field for Preis' }))
    const panel = screen.getByRole('complementary', { name: 'Column Preis' })
    fireEvent.click(within(panel).getByRole('treeitem', { name: 'Price' }))

    fireEvent.click(screen.getByRole('radio', { name: 'Use a Data Importer configuration' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Map the columns here' }))

    expect(screen.getByRole('button', { name: 'Field for Preis' }).textContent).toContain('Price')
  })
})
