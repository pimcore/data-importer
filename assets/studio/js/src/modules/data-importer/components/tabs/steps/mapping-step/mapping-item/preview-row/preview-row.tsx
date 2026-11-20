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
import { Spin, Tooltip } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useMappingPreview } from '../use-mapping-previews'
import { type MappingWarning } from '../mapping-warning'
import { useStyles } from './preview-row.styles'

export interface PreviewRowProps {
  fieldIndex: number
  expanded: boolean
  warning: MappingWarning | null
}

const warningKeys: Record<MappingWarning, string> = {
  'advanced-setup': 'data-importer.mapping.preview-mode.warning.advanced-setup',
  'further-configuration': 'data-importer.mapping.preview-mode.warning.further-configuration'
}

/** The "Preview" row under a mapping: what it produces for the selected record; nothing while preview mode is off */
export const PreviewRow = ({ fieldIndex, expanded, warning }: PreviewRowProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { styles, cx } = useStyles()
  const preview = useMappingPreview(fieldIndex)

  if (preview === null) return null

  const content = ((): React.ReactNode => {
    // a failed request fails every row, warnings included
    if (preview.status === 'failed') {
      return <span className={ styles.muted }>{ t('data-importer.mapping.preview-mode.unavailable') }</span>
    }
    if (warning !== null) {
      return <span className={ styles.warning }>{ t(warningKeys[warning]) }</span>
    }
    if (preview.status === 'loading') {
      return <Spin size="small" />
    }
    if (preview.value === '') {
      return <span className={ styles.muted }>{ t('data-importer.mapping.preview-mode.no-value') }</span>
    }

    return (
      <Tooltip title={ preview.value }>
        <span className={ styles.value }>{ preview.value }</span>
      </Tooltip>
    )
  })()

  return (
    <fieldset
      aria-busy={ warning === null && preview.status === 'loading' }
      aria-label={ t('data-importer.mapping.preview-mode.preview') }
      className={ cx(styles.row, expanded && styles.rowExpanded) }
      data-preview-row
    >
      <span className={ styles.label }>{ t('data-importer.mapping.preview-mode.preview') }</span>
      { content }
    </fieldset>
  )
}
