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
import { autoWidthColumnOf, fieldColumnSize, ImportObjectsWidget, PAGE_SIZE } from './import-objects-widget'

jest.mock('@pimcore/studio-ui-bundle/api', () => ({ getPrefix: () => '/pimcore-studio/api' }))
jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportListRunObjectsQuery: (...args: unknown[]) => mockListObjects(...args)
}))

const mockListObjects = jest.fn()

const answer = {
  totalItems: 298,
  fields: [{ key: 'sku', label: 'SKU' }, { key: 'name', label: 'Name (en)' }],
  items: [
    { id: 11, fullPath: '/Products/Outdoor/NH-1001', modificationDate: null, values: ['NH-1001', 'Trail jacket'] },
    { id: 12, fullPath: '/Products/Outdoor/NH-1002', modificationDate: null, values: ['NH-1002', null] }
  ]
}

const renderWidget = (): void => {
  render(<ImportObjectsWidget
    created={ 11 }
    fileId="file-1"
    fileName="northhike-autumn.xlsx"
    finishedAt={ 1_800_000_000 }
    kind="updated"
    updated={ 298 }
    userName="Jo Doe"
         />)
}

// jsdom draws no text: a header is as wide as ten pixels per letter
beforeEach(() => {
  const measureText = (text: string): { width: number } => ({ width: text.length * 10 })
  jest.spyOn(HTMLCanvasElement.prototype, 'getContext')
    .mockReturnValue({ font: '', measureText } as unknown as CanvasRenderingContext2D)
})

afterEach(() => {
  cleanup()
  jest.resetAllMocks()
  jest.restoreAllMocks()
})

describe('the objects an import saved', () => {
  it('lists the updated objects with the fields the file wrote', () => {
    mockListObjects.mockReturnValue({ data: answer, isLoading: false, isFetching: false, isError: false })
    renderWidget()

    expect(mockListObjects).toHaveBeenLastCalledWith({ fileId: 'file-1', kind: 'updated', page: 1, pageSize: PAGE_SIZE })
    expect(screen.getAllByRole('columnheader').map((header) => header.textContent)).toEqual(['SKU', 'Name (en)', 'Modified'])
    expect(within(screen.getByText('NH-1001').closest('tr') as HTMLElement).getByText('Trail jacket')).toBeTruthy()
    expect(screen.getByRole('radio', { name: 'Updated 298' }).getAttribute('aria-checked')).toBe('true')
    expect(screen.getByText(/^Imported .* by Jo Doe · columns: the fields the file wrote$/)).toBeTruthy()
    expect(screen.getByText('Total 298 items')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Export CSV' }).getAttribute('href'))
      .toBe('/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/file-1/run/objects/export?kind=updated')
  })

  it('switches to the created objects from their first page, and exports those', () => {
    mockListObjects.mockReturnValue({ data: answer, isLoading: false, isFetching: false, isError: false })
    renderWidget()

    fireEvent.click(screen.getByRole('button', { name: 'next page' }))
    expect(mockListObjects).toHaveBeenLastCalledWith({ fileId: 'file-1', kind: 'updated', page: 2, pageSize: PAGE_SIZE })

    fireEvent.click(screen.getByRole('radio', { name: 'Created 11' }))
    expect(mockListObjects).toHaveBeenLastCalledWith({ fileId: 'file-1', kind: 'created', page: 1, pageSize: PAGE_SIZE })
    expect(screen.getByRole('link', { name: 'Export CSV' }).getAttribute('href')).toContain('?kind=created')
  })

  it('shows the path where the file wrote no field the list can read', () => {
    mockListObjects.mockReturnValue({ data: { ...answer, fields: [] }, isLoading: false, isFetching: false, isError: false })
    renderWidget()

    expect(screen.getAllByRole('columnheader').map((header) => header.textContent)).toEqual(['Path', 'Modified'])
    expect(screen.getByText('/Products/Outdoor/NH-1001')).toBeTruthy()
  })

  it('says so when the import can no longer be listed', () => {
    mockListObjects.mockReturnValue({ data: undefined, isLoading: false, isFetching: false, isError: true })
    renderWidget()

    expect(screen.getByRole('alert').textContent).toContain('can no longer be listed')
    expect(screen.queryByRole('table')).toBeNull()
  })
})

describe('fieldColumnSize', () => {
  it('keeps a short text at the shared width', () => {
    expect(fieldColumnSize(20, 8)).toBe(48)
  })

  it('widens a column to its measured text and the cell padding', () => {
    expect(fieldColumnSize(97.3, 8)).toBe(118)
  })
})

describe('autoWidthColumnOf', () => {
  it('gives the width left to the narrowest column', () => {
    expect(autoWidthColumnOf([80, 64, 118, 64])).toBe(1)
  })
})

describe('the object list columns', () => {
  it('fixes every column to its widest header or value and lets only the narrowest take the width left', () => {
    mockListObjects.mockReturnValue({
      data: {
        totalItems: 2,
        fields: [{ key: 'series', label: 'Series' }, { key: 'name', label: 'Name' }, { key: 'manufacturer', label: 'Manufacturer' }],
        items: [
          { id: 1, fullPath: '/upload/new/C769-10', modificationDate: null, values: ['C769-10', 'Roadster', 'MG'] },
          { id: 2, fullPath: '/upload/new/C769-1', modificationDate: null, values: ['C769-1', 'Coupe', 'Alfa Romeo'] }
        ]
      },
      isLoading: false,
      isFetching: false,
      isError: false
    })
    renderWidget()

    // ten pixels a letter, the padding on both sides and the slack
    const headers = screen.getAllByRole('columnheader')
    expect(headers.map((header) => header.getAttribute('data-size'))).toEqual(['90', '100', '140', '100'])
    expect(headers.map((header) => header.getAttribute('data-auto-width'))).toEqual(['true', null, null, null])
  })
})
