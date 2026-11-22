/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container, type AbstractModule } from '@pimcore/studio-ui-bundle'
import { serviceIds } from '@pimcore/studio-ui-bundle/app'
import {
  componentConfig,
  contextMenuConfig,
  type ComponentRegistryInterface,
  type ContextMenuRegistry,
  type DataObjectTreeContextMenuProps
} from '@pimcore/studio-ui-bundle/modules/app'
import { AdHocImportHost } from './ad-hoc-import-host'
import { useImportFileDivider, useImportFileMenuItem, useShowsAnyItem } from './use-import-file-menu-item'

export const AdHocImportModule: AbstractModule = {
  onInit () {
    const config = contextMenuConfig.dataObjectTree

    const contextMenu = container.get<ContextMenuRegistry>(serviceIds['App/ContextMenuRegistry/ContextMenuRegistry'])
    const dividerBeforePriority = config.priority.addFolder + 1
    // read at render: other modules may register after this one
    const providersAbove = (): ReturnType<ContextMenuRegistry['getSlotProviders']> => contextMenu.getSlotProviders(config.name)
      .filter(provider => (provider.priority ?? 999) < dividerBeforePriority)
    // after what else adds to the folder, between two dividers
    contextMenu.registerToSlot(config.name, {
      name: 'importFileDividerBefore',
      priority: dividerBeforePriority,
      useMenuItem: (context: DataObjectTreeContextMenuProps) =>
        useImportFileDivider(context.target, 'importFileDividerBefore', useShowsAnyItem(providersAbove(), context))
    })
    contextMenu.registerToSlot(config.name, {
      name: 'importFile',
      priority: config.priority.addFolder + 2,
      useMenuItem: (context: DataObjectTreeContextMenuProps) => useImportFileMenuItem(context.target)
    })
    contextMenu.registerToSlot(config.name, {
      name: 'importFileDividerAfter',
      priority: config.priority.addFolder + 3,
      useMenuItem: (context: DataObjectTreeContextMenuProps) => useImportFileDivider(context.target, 'importFileDividerAfter')
    })

    // always mounted, so the dialog outlives the menu; it renders nothing until opened
    container.get<ComponentRegistryInterface>(serviceIds['App/ComponentRegistry/ComponentRegistry']).registerToSlot(componentConfig.leftSidebar.slot.name, {
      name: 'dataImporterAdHocImport',
      component: AdHocImportHost
    })
  }
}
