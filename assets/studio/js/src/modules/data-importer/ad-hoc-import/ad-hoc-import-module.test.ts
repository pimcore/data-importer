/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { AdHocImportModule } from '.'
import { AdHocImportHost } from './ad-hoc-import-host'

const mockContextMenu = { registerToSlot: jest.fn(), getSlotProviders: jest.fn() }
const mockComponents = { registerToSlot: jest.fn() }

jest.mock('@pimcore/studio-ui-bundle', () => ({
  container: { get: (id: string) => id === 'context-menu' ? mockContextMenu : mockComponents }
}))
jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  serviceIds: { 'App/ContextMenuRegistry/ContextMenuRegistry': 'context-menu', 'App/ComponentRegistry/ComponentRegistry': 'components' }
}))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  contextMenuConfig: { dataObjectTree: { name: 'data-object.tree', priority: { addFolder: 120, rename: 200 } } },
  componentConfig: { leftSidebar: { slot: { name: 'leftSidebar.slot' } } }
}))
jest.mock('./ad-hoc-import-host', () => ({ AdHocImportHost: () => null }))
jest.mock('./use-import-file-menu-item', () => ({
  useImportFileMenuItem: () => ({ key: 'importFile' }),
  useImportFileDivider: (_node: unknown, key: string, hasItemOnThatSide: boolean = true) => hasItemOnThatSide ? { type: 'divider', key } : null,
  useShowsAnyItem: (providers: Array<{ useMenuItem: (context: unknown) => { hidden?: boolean } }>, context: unknown) =>
    providers.some(provider => provider.useMenuItem(context).hidden !== true)
}))

interface Entry { slot: string, name: string, priority: number, useMenuItem: (context: object) => unknown }

const registeredEntries = (): Entry[] => mockContextMenu.registerToSlot.mock.calls
  .map(([slot, entry]: [string, Omit<Entry, 'slot'>]) => ({ slot, ...entry }))
  .sort((a: Entry, b: Entry) => a.priority - b.priority)

describe('AdHocImportModule', () => {
  it('adds the menu item to the data object tree between two dividers, after adding and before renaming', () => {
    mockContextMenu.registerToSlot.mockClear()
    mockContextMenu.getSlotProviders.mockReturnValue([{ name: 'addFolder', priority: 120, useMenuItem: () => ({ key: 'addFolder' }) }])
    AdHocImportModule.onInit()

    const entries = registeredEntries()
    expect(entries.map(({ slot }) => slot)).toEqual(['data-object.tree', 'data-object.tree', 'data-object.tree'])
    expect(entries.map(({ useMenuItem }) => useMenuItem({ target: {} }))).toEqual([
      { type: 'divider', key: 'importFileDividerBefore' },
      { key: 'importFile' },
      { type: 'divider', key: 'importFileDividerAfter' }
    ])
    expect(entries[0].priority).toBeGreaterThan(120)
    expect(entries[2].priority).toBeLessThan(200)
  })

  it('leaves out the divider above the menu item when no item above it shows', () => {
    mockContextMenu.registerToSlot.mockClear()
    const useImportFile = jest.fn().mockReturnValue({ key: 'importFile' })
    mockContextMenu.getSlotProviders.mockReturnValue([
      { name: 'addObject', priority: 100, useMenuItem: () => ({ key: 'addObject', hidden: true }) },
      { name: 'addFolder', priority: 120, useMenuItem: () => ({ key: 'addFolder', hidden: true }) },
      { name: 'importFile', priority: 122, useMenuItem: useImportFile },
      { name: 'rename', priority: 200, useMenuItem: () => ({ key: 'rename' }) }
    ])
    AdHocImportModule.onInit()

    const before = registeredEntries().find(({ name }) => name === 'importFileDividerBefore')
    expect(before?.useMenuItem({ target: {} })).toBeNull()
    expect(mockContextMenu.getSlotProviders).toHaveBeenCalledWith('data-object.tree')
    // only the items above it decide, not the entry itself or those below
    expect(useImportFile).not.toHaveBeenCalled()
  })

  it('keeps the host of the dialog mounted', () => {
    AdHocImportModule.onInit()

    const [slot, entry] = mockComponents.registerToSlot.mock.calls[0]
    expect(slot).toBe('leftSidebar.slot')
    expect(entry.component).toBe(AdHocImportHost)
  })
})
