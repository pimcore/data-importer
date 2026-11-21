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
import { useImportFileDivider, useImportFileMenuItem, useShowsAnyItem, AD_HOC_IMPORT_PERMISSION } from './use-import-file-menu-item'

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

    expect(result.current).toEqual({ folderId: 77, folderPath: '/Products/Outdoor', folderLabel: 'Outdoor' })
  })

  it('falls back to the label for a node without its object', () => {
    const { result } = renderHook(() => useAdHocImportTarget())

    const menuItem = item(folder({ label: 'home', metaData: {} }))
    act(() => { menuItem.onClick() })

    expect(result.current).toEqual({ folderId: 77, folderPath: '/home', folderLabel: 'home' })
  })

  it('sets the entry off with a divider where it shows', () => {
    expect(renderHook(() => useImportFileDivider(folder(), 'before')).result.current).toEqual({ type: 'divider', key: 'before' })
  })

  it('leaves out the divider where the entry does not show', () => {
    expect(renderHook(() => useImportFileDivider(folder({ type: 'object' }), 'before')).result.current).toBeNull()

    mockIsAllowed.mockReturnValue(false)
    expect(renderHook(() => useImportFileDivider(folder(), 'before')).result.current).toBeNull()
  })

  it('leaves out the divider where the menu has no item on its side', () => {
    expect(renderHook(() => useImportFileDivider(folder(), 'before', false)).result.current).toBeNull()
  })
})

describe('whether the menu shows an item', () => {
  const provider = (item: object | null): { useMenuItem: () => any } => ({ useMenuItem: () => item })

  it('is true when one item shows', () => {
    const providers = [provider({ key: 'addObject', hidden: true }), provider({ key: 'addFolder', hidden: false })]

    expect(renderHook(() => useShowsAnyItem(providers, {})).result.current).toBe(true)
  })

  it('is false when every item is hidden, a divider or left out', () => {
    const providers = [provider({ key: 'addObject', hidden: true }), provider({ type: 'divider', key: 'line' }), provider(null)]

    expect(renderHook(() => useShowsAnyItem(providers, {})).result.current).toBe(false)
    expect(renderHook(() => useShowsAnyItem([], {})).result.current).toBe(false)
  })

  it('asks each provider with the menu context', () => {
    const useMenuItem = jest.fn().mockReturnValue({ key: 'addFolder' })
    const context = { target: folder() }

    renderHook(() => useShowsAnyItem([{ useMenuItem }], context))

    expect(useMenuItem).toHaveBeenCalledWith(context)
  })
})
