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
import { useImportFileMenuItem } from './use-import-file-menu-item'

export const AdHocImportModule: AbstractModule = {
  onInit () {
    const config = contextMenuConfig.dataObjectTree

    container.get<ContextMenuRegistry>(serviceIds['App/ContextMenuRegistry/ContextMenuRegistry']).registerToSlot(config.name, {
      name: 'importFile',
      // beside what else adds to the folder
      priority: config.priority.addFolder + 1,
      useMenuItem: (context: DataObjectTreeContextMenuProps) => useImportFileMenuItem(context.target)
    })

    // always mounted, so the dialog outlives the menu; it renders nothing until opened
    container.get<ComponentRegistryInterface>(serviceIds['App/ComponentRegistry/ComponentRegistry']).registerToSlot(componentConfig.leftSidebar.slot.name, {
      name: 'dataImporterAdHocImport',
      component: AdHocImportHost
    })
  }
}
