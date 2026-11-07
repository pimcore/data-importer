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
jest.mock('./use-import-file-menu-item', () => ({ useImportFileMenuItem: () => ({ key: 'importFile' }) }))

describe('AdHocImportModule', () => {
  it('adds the menu item to the data object tree, between adding and renaming', () => {
    AdHocImportModule.onInit()

    const [slot, entry] = mockContextMenu.registerToSlot.mock.calls[0]
    expect(slot).toBe('data-object.tree')
    expect(entry.priority).toBeGreaterThan(120)
    expect(entry.priority).toBeLessThan(200)
    expect(entry.useMenuItem({ target: {} })).toEqual({ key: 'importFile' })
  })

  it('keeps the host of the dialog mounted', () => {
    AdHocImportModule.onInit()

    const [slot, entry] = mockComponents.registerToSlot.mock.calls[0]
    expect(slot).toBe('leftSidebar.slot')
    expect(entry.component).toBe(AdHocImportHost)
  })
})
