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
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { canContinue, initialObjects, type InspectedFile, type ObjectsSettings } from './file-step-state'
import { ObjectsPanel } from './objects-panel'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/api/class-definition', () => ({
  api: {
    endpoints: {
      classDefinitionCollectionCreatable: {
        useQuery: () => ({ data: { items: [{ id: 'PROD', name: 'Product' }] }, isLoading: false })
      }
    }
  }
}))
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportLoadMatchFieldsQuery: () => ({
    currentData: { attributes: [{ key: 'sku', title: 'SKU', localized: false }] },
    isFetching: false
  })
}))
jest.mock('../../components/tabs/steps/mapping-step/hooks/use-mapping-step-loader.types', () => ({
  parseClassAttribute: (attribute: unknown) => attribute
}))

const inspected: InspectedFile = {
  maxRows: 5000,
  fileId: 'file-1',
  type: 'csv',
  hasHeaderRow: true,
  sheets: [],
  rowCount: 1,
  columns: [{ dataIndex: 'Artikelnr', label: 'Artikelnr' }],
  firstRows: [['NH-1']]
}

const Stateful = ({ onObjects }: { onObjects: (objects: ObjectsSettings) => void }): React.JSX.Element => {
  const [objects, setObjects] = useState<ObjectsSettings>({ ...initialObjects, classId: 'PROD' })

  return (
    <ObjectsPanel
      inspected={ inspected }
      objects={ objects }
      onChange={ (next) => { setObjects(next); onObjects(next) } }
    />
  )
}

afterEach(cleanup)

describe('ObjectsPanel', () => {
  it('offers not to match, which needs only the column naming the new objects', () => {
    let objects: ObjectsSettings = { ...initialObjects, classId: 'PROD' }
    render(<Stateful onObjects={ (next) => { objects = next } } />)

    expect(screen.getAllByRole('radio').map((radio) => radio.textContent)).toEqual(['Object ID', 'Field', 'Don\'t match'])
    expect(screen.getByText('Found objects are updated where they are.')).toBeTruthy()

    fireEvent.click(screen.getByRole('radio', { name: 'Don\'t match' }))

    expect(objects.matchBy).toBe('none')
    expect(screen.getByRole('radio', { name: 'Don\'t match' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.queryByRole('combobox', { name: 'Field' })).toBeNull()
    expect(screen.getByText('Each row creates a new object, named by the column\'s value.')).toBeTruthy()
    expect(screen.queryByText('Found objects are updated where they are.')).toBeNull()

    fireEvent.change(screen.getByRole('combobox', { name: 'key from column' }), { target: { value: 'Artikelnr' } })

    expect(objects).toMatchObject({ matchBy: 'none', matchColumn: 'Artikelnr' })
    expect(objects.matchField).toBeUndefined()
    expect(canContinue({ kind: 'inspected', file: { name: 'prices.csv', size: 10 }, inspected }, objects)).toBe(true)
  })

  it('matches by a field from a column', () => {
    render(<Stateful onObjects={ () => {} } />)

    expect(screen.getByRole('combobox', { name: 'Field' })).toBeTruthy()
    expect(screen.getByRole('combobox', { name: 'from column' })).toBeTruthy()
    expect(screen.queryByRole('combobox', { name: 'key from column' })).toBeNull()
  })
})
