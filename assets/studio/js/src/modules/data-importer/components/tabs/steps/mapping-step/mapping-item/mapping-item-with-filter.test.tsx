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
import { Form } from 'antd'
import { cleanup, render, screen } from '@testing-library/react'
import { MappingItemContextProvider } from '../mapping-item-context'
import { MappingItemWithFilter } from './mapping-item-with-filter'

jest.mock('./mapping-drop-zone', () => ({
  MappingDropZone: () => null
}))
// the row's editor is out of scope; its stand-in shows what the row hands it
jest.mock('./mapping-item', () => ({
  MappingItem: ({ classId, columnHeaderOptions, itemLabel }: { classId?: string, columnHeaderOptions: Array<{ value: string }>, itemLabel?: string }) => (
    <span data-testid="item">{ `${itemLabel}@${classId}:${columnHeaderOptions.map(o => o.value).join(',')}` }</span>
  )
}))

afterEach(cleanup)

describe('MappingItemWithFilter', () => {
  it('hands its row the class and the source columns of the mapping step', () => {
    render(
      <Form initialValues={ { mappingConfig: [{ label: 'SKU', dataSourceIndex: ['sku'] }] } }>
        <MappingItemContextProvider value={ { classId: 'Product', columnHeaderOptions: [{ value: 'sku', label: 'SKU' }], attributesMap: {}, sourceRows: [] } }>
          <MappingItemWithFilter
            activeFilter={ null }
            add={ () => undefined }
            expanded={ false }
            fieldIndex={ 0 }
            insertIndex={ 0 }
            isNew={ false }
            mappingId="m1"
            onDropped={ () => undefined }
            onInsertItem={ () => undefined }
            onRemoveItem={ () => undefined }
            onToggle={ () => undefined }
            remove={ () => undefined }
          />
        </MappingItemContextProvider>
      </Form>
    )

    expect(screen.getByTestId('item').textContent).toBe('SKU@Product:sku')
  })
})
