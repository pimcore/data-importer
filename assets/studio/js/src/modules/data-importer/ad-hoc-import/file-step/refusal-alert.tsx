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
import { Alert } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from './file-step-state'

export interface RefusalAlertProps {
  inspected: InspectedFile
}

export const RefusalAlert = ({ inspected }: RefusalAlertProps): React.JSX.Element | null => {
  const { t, i18n } = useTranslation()

  switch (inspected.refusal) {
    case 'too-many-rows':
      return (
        <Alert
          description={ t('data-importer.ad-hoc-import.refused.too-many-rows-hint') }
          message={ t('data-importer.ad-hoc-import.refused.too-many-rows', {
            rows: inspected.rowCount.toLocaleString(i18n.language),
            limit: inspected.maxRows.toLocaleString(i18n.language)
          }) }
          style={ { maxWidth: 560 } }
          type="error"
        />
      )
    case 'unreadable':
      return (
        <Alert
          message={ t('data-importer.ad-hoc-import.refused.unreadable', { type: (inspected.type ?? '').toUpperCase() }) }
          style={ { maxWidth: 560 } }
          type="error"
        />
      )
    case 'wrong-format':
      return (
        <Alert
          message={ t('data-importer.ad-hoc-import.refused.wrong-format') }
          style={ { maxWidth: 560 } }
          type="error"
        />
      )
    default:
      return null
  }
}
