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
import { act, cleanup, render, screen } from '@testing-library/react'
import { AdHocImportHost } from './ad-hoc-import-host'
import { closeAdHocImport, openAdHocImport } from './dialog-store'

jest.mock('./ad-hoc-import-dialog', () => ({
  AdHocImportDialog: ({ target, onClose }: { target: { folderPath: string }, onClose: () => void }) => (
    <button
      onClick={ onClose }
      type="button"
    >
      { target.folderPath }
    </button>
  )
}))

afterEach(() => {
  cleanup()
  act(() => { closeAdHocImport() })
})

describe('AdHocImportHost', () => {
  it('renders nothing until a folder opens the dialog', () => {
    const { container } = render(<AdHocImportHost />)
    expect(container.innerHTML).toBe('')

    act(() => { openAdHocImport({ folderId: 77, folderPath: '/Products/Outdoor', folderLabel: 'Outdoor' }) })

    expect(screen.getByRole('button', { name: '/Products/Outdoor' })).toBeTruthy()
  })

  it('renders nothing again once the dialog closes', () => {
    const { container } = render(<AdHocImportHost />)
    act(() => { openAdHocImport({ folderId: 77, folderPath: '/Products/Outdoor', folderLabel: 'Outdoor' }) })

    act(() => { screen.getByRole('button').click() })

    expect(container.innerHTML).toBe('')
  })
})
