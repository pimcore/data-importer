/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type WidgetManagerTabConfig } from '@pimcore/studio-ui-bundle/modules/widget-manager'
import { type ImportRun, type ObjectsKind } from '../import-step/import-state'
import { type ImportObjectsWidgetProps } from './import-objects-widget'

export const IMPORT_OBJECTS_WIDGET = 'data-importer-ad-hoc-import-objects'

// one tab per imported file: opening it again shows the tab already open
export const importObjectsTab = (fileId: string, run: ImportRun, kind: ObjectsKind, title: string): WidgetManagerTabConfig => {
  const props: ImportObjectsWidgetProps = {
    fileId,
    fileName: run.fileName,
    userName: run.userName,
    finishedAt: run.finishedAt,
    updated: run.updated,
    created: run.created,
    kind
  }

  return {
    name: title,
    id: `${IMPORT_OBJECTS_WIDGET}-${fileId}`,
    component: IMPORT_OBJECTS_WIDGET,
    config: {
      label: title,
      icon: { type: 'name', value: 'list' },
      ...props
    }
  }
}
