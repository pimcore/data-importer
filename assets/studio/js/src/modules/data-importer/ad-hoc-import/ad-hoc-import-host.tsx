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
import { AdHocImportDialog } from './ad-hoc-import-dialog'
import { closeAdHocImport, useAdHocImportTarget } from './dialog-store'

export const AdHocImportHost = (): React.JSX.Element | null => {
  const target = useAdHocImportTarget()

  if (target === null) {
    return null
  }

  return (
    <AdHocImportDialog
      // a new import starts from nothing
      key={ target.folderId }
      onClose={ closeAdHocImport }
      target={ target }
    />
  )
}
