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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Icon, useTreePermission, type ItemType, type TreeNodeProps } from '@pimcore/studio-ui-bundle/components'
import { isAllowed } from '@pimcore/studio-ui-bundle/modules/auth'
import { checkElementPermission } from '@pimcore/studio-ui-bundle/modules/element'
import { TreePermission } from '@pimcore/studio-ui-bundle/modules/perspectives'
import { openAdHocImport } from './dialog-store'

export const AD_HOC_IMPORT_PERMISSION = 'plugin_data_importer_ad_hoc_import'

const useCanImportFile = (node: TreeNodeProps): boolean => {
  const { isTreeActionAllowed } = useTreePermission()

  return node.type === 'folder' &&
    isAllowed(AD_HOC_IMPORT_PERMISSION) &&
    isTreeActionAllowed(TreePermission.AddObject) &&
    checkElementPermission(node.permissions, 'create') &&
    checkElementPermission(node.permissions, 'save')
}

export const useImportFileMenuItem = (node: TreeNodeProps): ItemType => {
  const { t } = useTranslation()

  return {
    label: t('data-importer.ad-hoc-import.menu-item'),
    key: 'importFile',
    icon: <Icon value="upload-import" />,
    hidden: !useCanImportFile(node),
    onClick: () => {
      openAdHocImport({
        folderId: Number.parseInt(node.id),
        folderPath: node.metaData?.dataObject?.fullPath ?? `/${node.label}`,
        folderLabel: node.label
      })
    }
  }
}

// sets the entry off from adding above and editing below; only where the entry shows and the menu has an item on that side
export const useImportFileDivider = (node: TreeNodeProps, key: string, hasItemOnThatSide: boolean = true): ItemType => {
  return useCanImportFile(node) && hasItemOnThatSide ? { type: 'divider', key } : null
}

interface MenuItemProvider {
  useMenuItem: (context: any) => ItemType | null
}

// the menu drops hidden items but keeps a divider at its edge, so a divider needs a visible item beside it
export const useShowsAnyItem = (providers: MenuItemProvider[], context: unknown): boolean => {
  return providers
    .map(provider => provider.useMenuItem(context) as { type?: string, hidden?: boolean } | null)
    .some(item => item !== null && item !== undefined && item.type !== 'divider' && item.hidden !== true)
}
