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

export const useImportFileMenuItem = (node: TreeNodeProps): ItemType => {
  const { t } = useTranslation()
  const { isTreeActionAllowed } = useTreePermission()

  return {
    label: t('data-importer.ad-hoc-import.menu-item'),
    key: 'importFile',
    icon: <Icon value="upload-import" />,
    hidden: node.type !== 'folder' ||
      !isAllowed(AD_HOC_IMPORT_PERMISSION) ||
      !isTreeActionAllowed(TreePermission.AddObject) ||
      !checkElementPermission(node.permissions, 'create') ||
      !checkElementPermission(node.permissions, 'save'),
    onClick: () => {
      openAdHocImport({
        folderId: Number.parseInt(node.id),
        folderPath: node.metaData?.dataObject?.fullPath ?? `/${node.label}`
      })
    }
  }
}
