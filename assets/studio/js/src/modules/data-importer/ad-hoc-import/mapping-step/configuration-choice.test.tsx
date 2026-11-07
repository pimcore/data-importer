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
import { type ImportConfiguration, type MapWith } from './configuration-state'
import { ConfigurationChoice } from './configuration-choice'
import { createMapping, type LoadedMapping } from './mapping-state'
import { MappingStep } from './mapping-step'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
// the mapping step loads the transformations modal, which no test here opens
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({}))
jest.mock('../../data-importer-api-slice-enhanced', () => ({}))

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
  fields: [{ key: 'price', title: 'Price', localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true }],
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

const choose = (name: string): void => {
  fireEvent.click(card(name))
}

const detailOf = (shown: HTMLElement): string | null | undefined =>
  document.getElementById(shown.getAttribute('aria-describedby') ?? '')?.textContent

afterEach(() => {
  cleanup()
})

describe('the mapping with a configuration', () => {
  it('offers the configurations instead of the columns', () => {
    useConfiguration({ configurations: [configuration(), configuration({ name: 'prices' })] })

    expect(screen.getByRole('radio', { name: 'Use a Data Importer configuration' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.queryByRole('list', { name: 'Columns of the file' })).toBeNull()
    expect(cards().map((shown) => shown.getAttribute('aria-label'))).toEqual(['products', 'prices'])
    expect(cards().map(detailOf)).toEqual(['Product', 'Product'])
    expect(screen.getByText('Only those you may read in the Data Hub')).toBeTruthy()
    expect(screen.getByText('Choose a configuration')).toBeTruthy()
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

  it('shows the class, the match and the folder of the chosen one, which replace those of the file step', () => {
    useConfiguration({ configurations: [configuration()] })
    choose('products')

    const summary = within(screen.getByRole('region', { name: 'products' }))
    expect(summary.getByText('Its class, its match and its folder for new objects are used instead of those of the file step.')).toBeTruthy()
    expect(summary.getByText('Product')).toBeTruthy()
    expect(summary.getByText('name (de) from column Name DE')).toBeTruthy()
    expect(summary.getByText('/Products & more/Imported')).toBeTruthy()
    expect(screen.queryByText(/Columns it reads/)).toBeNull()
  })

  it('names the match of one without a language, and of one by the object ID', () => {
    useConfiguration({
      configurations: [
        configuration({ matchLanguage: null }),
        configuration({ name: 'by-id', matchBy: 'id', matchField: null, matchLanguage: null, matchColumn: 'Artikelnr', className: null })
      ]
    })
    choose('products')
    expect(screen.getByText('name from column Name DE')).toBeTruthy()

    choose('by-id')
    const summary = within(screen.getByRole('region', { name: 'by-id' }))
    expect(summary.getByText('Object ID from column Artikelnr')).toBeTruthy()
    expect(summary.getByText('PROD')).toBeTruthy()
  })

  it('warns of the columns it reads that the file does not have, unescaped', () => {
    useConfiguration({ configurations: [configuration({ columns: ['Name DE', 'EAN', 'Weight & size'] })] })
    choose('products')

    expect(screen.getByText('Columns it reads that the file does not have: EAN, Weight & size')).toBeTruthy()
  })

  it('finds the columns of one that names them by position in a file with a header row', () => {
    useConfiguration({ configurations: [configuration({ columnsBy: 'position', matchColumn: '1', columns: ['1', '2', '7'] })] })
    choose('products')

    expect(screen.getByText('name (de) from column Name DE')).toBeTruthy()
    expect(screen.getByText('Columns it reads that the file does not have: 7')).toBeTruthy()
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
    fireEvent.change(screen.getByLabelText('Field for Preis'), { target: { value: 'price' } })

    fireEvent.click(screen.getByRole('radio', { name: 'Use a Data Importer configuration' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Map the columns here' }))

    expect(screen.getByLabelText<HTMLSelectElement>('Field for Preis').value).toBe('price')
  })
})
