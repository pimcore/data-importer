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
import { FileCard, type FileCardProps } from './file-card'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: { barColor: 'primary' } })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ formatDataUnit: (bytes: number) => `${bytes} B` }))

afterEach(cleanup)

const uploading = (loaded: number, total: number): FileCardProps['phase'] =>
  ({ kind: 'uploading', file: { name: 'products.csv', size: total }, loaded, total })

describe('FileCard', () => {
  it('keeps the bar from turning green when the upload is complete, as the server still reads the file', () => {
    render(<FileCard
      onRemove={ () => {} }
      phase={ uploading(2048, 2048) }
           />)

    const bar = screen.getByRole('progressbar')
    expect(bar.getAttribute('value')).toBe('100')
    expect(bar.getAttribute('data-status')).toBe('normal')
  })

  it('draws the bar in the primary colour, not antd\'s info blue', () => {
    render(<FileCard
      onRemove={ () => {} }
      phase={ uploading(512, 2048) }
           />)

    expect(screen.getByRole('progressbar').getAttribute('data-stroke')).toBe('primary')
  })
})
