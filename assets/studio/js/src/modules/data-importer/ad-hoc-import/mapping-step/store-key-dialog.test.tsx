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
import { StoreKeyDialog, storeKeyOf, useAdHocStoreKeys } from './store-key-dialog'

const mockKeysQuery = jest.fn()
jest.mock('../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterAdHocImportLoadClassificationStoreKeysQuery: (...args: unknown[]) => mockKeysQuery(...args)
}))
// the configuration's key modal, shown with the keys its query gives
jest.mock('../../components/tabs/steps/advanced-mapping-modal/step-target/classification-store-key-modal/classification-store-key-modal', () => ({
  ClassificationStoreKeyModal: ({ classId, fieldName, transformationResultType, useKeysQuery, onSelect, onClose }: {
    classId: string
    fieldName: string
    transformationResultType: string
    useKeysQuery: (arg: object, options: { skip: boolean }) => { data?: { data: Array<{ id: string, groupName?: string, keyName?: string }> } }
    onSelect: (keyId: string, row?: object) => void
    onClose: () => void
  }) => {
    const { data } = useKeysQuery({ classId, fieldName, transformationResultType, start: 0, limit: 10 }, { skip: false })

    return (
      <dialog open>
        { data?.data.map((row) => (
          <button
            key={ row.id }
            onClick={ () => { onSelect(row.id, row) } }
            type="button"
          >
            { row.keyName }
          </button>
        )) }
        <button
          onClick={ onClose }
          type="button"
        >
          Cancel
        </button>
      </dialog>
    )
  }
}))

afterEach(() => {
  cleanup()
  mockKeysQuery.mockReset()
})

describe('the key dialog of the ad hoc import', () => {
  it('asks its own endpoint for the keys of any type', () => {
    mockKeysQuery.mockReturnValue({ data: { data: [], total: 0 }, isFetching: false })

    const answer = useAdHocStoreKeys({ classId: 'PROD', fieldName: 'attributes', transformationResultType: 'default', start: 20, limit: 10, searchfilter: 'wid' }, { skip: false })

    expect(mockKeysQuery).toHaveBeenCalledWith({ classId: 'PROD', fieldName: 'attributes', start: 20, limit: 10, searchfilter: 'wid' }, { skip: false })
    expect(answer).toEqual({ data: { data: [], total: 0 }, isFetching: false })
  })

  it('names a key by its group and name', () => {
    expect(storeKeyOf('2-4', { groupName: 'Dimensions', keyName: 'Width' })).toEqual({ id: '2-4', label: 'Dimensions › Width' })
    expect(storeKeyOf('2-4')).toEqual({ id: '2-4', label: '2-4' })
    expect(storeKeyOf('2-4', { keyName: 'Width' })).toEqual({ id: '2-4', label: '2-4' })
  })

  it('gives the key chosen in the store of the class, or nothing when closed', () => {
    mockKeysQuery.mockReturnValue({ data: { data: [{ id: '2-4', groupName: 'Dimensions', keyName: 'Width' }], total: 1 }, isFetching: false })
    const onSelect = jest.fn()
    const onClose = jest.fn()

    render(
      <StoreKeyDialog
        classId="PROD"
        fieldName="attributes"
        onClose={ onClose }
        onSelect={ onSelect }
      />
    )
    expect(mockKeysQuery).toHaveBeenCalledWith(expect.objectContaining({ classId: 'PROD', fieldName: 'attributes' }), { skip: false })

    fireEvent.click(screen.getByRole('button', { name: 'Width' }))
    expect(onSelect).toHaveBeenCalledWith({ id: '2-4', label: 'Dimensions › Width' })

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onClose).toHaveBeenCalled()
  })
})
