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
import { cleanup, render, screen } from '@testing-library/react'
import { MappingItemTitle } from './mapping-item-title'

const annotations = new Map<string, { status: string }>()

jest.mock('../../../../../change-control/studio-form-annotations', () => ({
  useFormItemAnnotation: (name: Array<string | number>) => annotations.get(name.join('.'))
}))
jest.mock('@pimcore/change-control-bundle/sdk', () => ({
  ChangeTag: ({ status }: { status: string }) => <span data-testid="change-tag">{ status }</span>
}))
jest.mock('../mapping-step.styles', () => ({
  useStyles: () => ({ styles: { annotatedTitle: 'annotated', removedTitle: 'removed' } })
}))

afterEach(() => {
  cleanup()
  annotations.clear()
})

describe('MappingItemTitle', () => {
  it('shows the bare label on a row the review did not mark', () => {
    annotations.set('mappingConfig.1', { status: 'changed' })

    render(<MappingItemTitle
      fieldIndex={ 0 }
      label="SKU"
           />)

    expect(screen.getByText('SKU')).toBeTruthy()
    expect(screen.queryByTestId('change-tag')).toBeNull()
  })

  it.each(['added', 'changed', 'moved'])('marks a %s row with its change tag', (status) => {
    annotations.set('mappingConfig.2', { status })

    render(<MappingItemTitle
      fieldIndex={ 2 }
      label="Name"
           />)

    expect(screen.getByTestId('change-tag').textContent).toBe(status)
    expect(screen.getByText('Name').className).not.toContain('removed')
  })

  it('strikes the label of a removed row', () => {
    annotations.set('mappingConfig.0', { status: 'removed' })

    render(<MappingItemTitle
      fieldIndex={ 0 }
      label="Price"
           />)

    expect(screen.getByTestId('change-tag').textContent).toBe('removed')
    expect(screen.getByText('Price').className).toBe('removed')
  })
})
