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
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { ClassificationStoreKeyModal } from './classification-store-key-modal'

const mockDispatch = jest.fn()
const mockConfigKeys = jest.fn()

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useAppDispatch: () => mockDispatch,
  useTranslation: () => ({ t: (key: string) => key })
}))
jest.mock('./classification-store-key-modal.styles', () => ({ useStyles: () => ({ styles: {} }) }))
jest.mock('../../../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterClassificationstoreLoadKeysQuery: (...args: unknown[]) => mockConfigKeys(...args)
}))
jest.mock('../../../../../../data-importer-api-slice-enhanced', () => ({
  api: { util: { upsertQueryData: (...args: unknown[]) => ({ type: 'upsert', args }) } }
}))
// the grid selects a row by its id when the row is clicked
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Button: ({ children, disabled, onClick }: { children: React.ReactNode, disabled?: boolean, onClick?: () => void }) => (
    <button
      disabled={ disabled }
      onClick={ onClick }
      type="button"
    >
      { children }
    </button>
  ),
  Flex: ({ children }: { children: React.ReactNode }) => <div>{ children }</div>,
  Grid: ({ data, setRowId, onSelectedRowsChange }: {
    data: Array<{ keyName?: string }>
    setRowId: (row: object, index: number) => string
    onSelectedRowsChange: (selection: Record<string, boolean>) => void
  }) => (
    <ul>
      { data.map((row, index) => (
        <li key={ setRowId(row, index) }>
          <button
            onClick={ () => { onSelectedRowsChange({ [setRowId(row, index)]: true }) } }
            type="button"
          >
            { `row ${row.keyName}` }
          </button>
        </li>
      )) }
    </ul>
  ),
  Modal: ({ children, open }: { children: React.ReactNode, open: boolean }) => open ? <dialog open>{ children }</dialog> : null,
  Pagination: () => null,
  SearchInput: () => null,
  Text: ({ children }: { children: React.ReactNode }) => <span>{ children }</span>
}))

const width = { id: '2-4', keyId: 4, groupId: 2, keyName: 'Width', groupName: 'Dimensions' }

afterEach(() => {
  cleanup()
  mockConfigKeys.mockReset()
})

const apply = (): void => {
  fireEvent.click(screen.getByRole('button', { name: 'row Width' }))
  fireEvent.click(screen.getByRole('button', { name: 'common.apply-selection' }))
}

describe('the classification store key modal', () => {
  it('lists the keys of the configuration endpoint and gives the row chosen with its key', () => {
    mockConfigKeys.mockReturnValue({ data: { data: [width], total: 1 }, isFetching: false })
    const onSelect = jest.fn()

    render(
      <ClassificationStoreKeyModal
        classId="PROD"
        fieldName="attributes"
        onClose={ jest.fn() }
        onSelect={ onSelect }
        open
        transformationResultType="numeric"
      />
    )
    apply()

    expect(mockConfigKeys).toHaveBeenCalledWith(
      expect.objectContaining({ classId: 'PROD', fieldName: 'attributes', transformationResultType: 'numeric' }),
      { skip: false }
    )
    expect(onSelect).toHaveBeenCalledWith('2-4', width)
  })

  it('lists the keys a caller gives instead', () => {
    const useKeys = jest.fn().mockReturnValue({ data: { data: [width], total: 1 }, isFetching: false })
    const onSelect = jest.fn()

    render(
      <ClassificationStoreKeyModal
        classId="PROD"
        fieldName="attributes"
        onClose={ jest.fn() }
        onSelect={ onSelect }
        open
        transformationResultType=""
        useKeysQuery={ useKeys }
      />
    )
    apply()

    expect(mockConfigKeys).not.toHaveBeenCalled()
    expect(useKeys).toHaveBeenCalledWith(expect.objectContaining({ classId: 'PROD', fieldName: 'attributes' }), { skip: false })
    expect(onSelect).toHaveBeenCalledWith('2-4', width)
  })
})
