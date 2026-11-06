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
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { FileDropZone } from './file-drop-zone'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)

afterEach(cleanup)

describe('FileDropZone', () => {
  it('invites a file and hands over the chosen one without uploading it itself', async () => {
    const onFile = jest.fn()
    const { container } = render(<FileDropZone onFile={ onFile } />)

    expect(screen.getByText('Drag and drop an XLSX or CSV file here, or click to choose one')).toBeTruthy()

    const input = container.querySelector<HTMLInputElement>('input[type="file"]')!
    expect(input.accept).toBe('.xlsx,.csv')

    const file = new File(['sku'], 'prices.csv')
    fireEvent.change(input, { target: { files: [file] } })

    await waitFor(() => { expect(onFile).toHaveBeenCalledTimes(1) })
    expect(onFile.mock.calls[0][0].name).toBe('prices.csv')
  })
})
