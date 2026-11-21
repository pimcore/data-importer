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

const mockContextMenu = { registerToSlot: jest.fn() }
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
  useImportFileDivider: (_node: unknown, key: string) => ({ type: 'divider', key })
}))

describe('AdHocImportModule', () => {
  it('adds the menu item to the data object tree between two dividers, after adding and before renaming', () => {
    mockContextMenu.registerToSlot.mockClear()
    AdHocImportModule.onInit()

    const entries = mockContextMenu.registerToSlot.mock.calls
      .map(([slot, entry]: [string, { priority: number, useMenuItem: (context: object) => unknown }]) => ({ slot, ...entry }))
      .sort((a, b) => a.priority - b.priority)
    expect(entries.map(({ slot }) => slot)).toEqual(['data-object.tree', 'data-object.tree', 'data-object.tree'])
    expect(entries.map(({ useMenuItem }) => useMenuItem({ target: {} }))).toEqual([
      { type: 'divider', key: 'importFileDividerBefore' },
      { key: 'importFile' },
      { type: 'divider', key: 'importFileDividerAfter' }
    ])
    expect(entries[0].priority).toBeGreaterThan(120)
    expect(entries[2].priority).toBeLessThan(200)
  })

  it('keeps the host of the dialog mounted', () => {
    AdHocImportModule.onInit()

    const [slot, entry] = mockComponents.registerToSlot.mock.calls[0]
    expect(slot).toBe('leftSidebar.slot')
    expect(entry.component).toBe(AdHocImportHost)
  })
})
