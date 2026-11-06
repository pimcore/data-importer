/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { act, renderHook } from '@testing-library/react'
import type { TreeNodeProps } from '@pimcore/studio-ui-bundle/components'
import { closeAdHocImport, useAdHocImportTarget } from './dialog-store'
import { useImportFileMenuItem, AD_HOC_IMPORT_PERMISSION } from './use-import-file-menu-item'

jest.mock('@pimcore/studio-ui-bundle/app', () => jest.requireActual<object>('../../../../test/support/ad-hoc-import'))
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Icon: () => null,
  useTreePermission: () => ({ isTreeActionAllowed: (action: string) => action !== mockDeniedTreeAction })
}))
jest.mock('@pimcore/studio-ui-bundle/modules/auth', () => ({ isAllowed: (permission: string) => mockIsAllowed(permission) }))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  checkElementPermission: (permissions: Record<string, boolean>, key: string) => permissions[key]
}))
jest.mock('@pimcore/studio-ui-bundle/modules/perspectives', () => ({ TreePermission: { AddObject: 'addObject' } }))

let mockDeniedTreeAction: string | undefined
const mockIsAllowed = jest.fn()

const folder = (given: object = {}): TreeNodeProps => ({
  id: '77',
  type: 'folder',
  label: 'Outdoor',
  permissions: { create: true, save: true },
  metaData: { dataObject: { fullPath: '/Products/Outdoor' } },
  ...given
}) as unknown as TreeNodeProps

const item = (node: TreeNodeProps): { hidden: boolean, label: string, onClick: () => void } =>
  renderHook(() => useImportFileMenuItem(node)).result.current as unknown as { hidden: boolean, label: string, onClick: () => void }

beforeEach(() => {
  mockDeniedTreeAction = undefined
  mockIsAllowed.mockReturnValue(true)
})

afterEach(() => {
  act(() => { closeAdHocImport() })
})

describe('the menu item', () => {
  it('shows on a folder for a user with the right and create and save on it', () => {
    expect(item(folder())).toMatchObject({ hidden: false, label: 'Import file…' })
    expect(mockIsAllowed).toHaveBeenCalledWith(AD_HOC_IMPORT_PERMISSION)
  })

  it('does not show on an object', () => {
    expect(item(folder({ type: 'object' })).hidden).toBe(true)
  })

  it('does not show without the right', () => {
    mockIsAllowed.mockReturnValue(false)

    expect(item(folder()).hidden).toBe(true)
  })

  it.each([
    ['create', { create: false, save: true }],
    ['save', { create: true, save: false }]
  ])('does not show without %s on the folder', (_, permissions) => {
    expect(item(folder({ permissions })).hidden).toBe(true)
  })

  it('does not show where the perspective forbids adding objects', () => {
    mockDeniedTreeAction = 'addObject'

    expect(item(folder()).hidden).toBe(true)
  })

  it('opens the dialog for the folder', () => {
    const { result } = renderHook(() => useAdHocImportTarget())
    expect(result.current).toBeNull()

    const menuItem = item(folder())
    act(() => { menuItem.onClick() })

    expect(result.current).toEqual({ folderId: 77, folderPath: '/Products/Outdoor' })
  })

  it('falls back to the label for a node without its object', () => {
    const { result } = renderHook(() => useAdHocImportTarget())

    const menuItem = item(folder({ label: 'home', metaData: {} }))
    act(() => { menuItem.onClick() })

    expect(result.current).toEqual({ folderId: 77, folderPath: '/home' })
  })
})
