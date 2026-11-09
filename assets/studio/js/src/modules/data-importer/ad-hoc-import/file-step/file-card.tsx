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
import cn from 'classnames'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { IconButton, Progress } from '@pimcore/studio-ui-bundle/components'
import { formatProgress, formatSize } from './format'
import { useStyles } from './file-card.styles'
import { fileBadge, type FilePhase } from './file-step-state'

export interface FileCardProps {
  phase: FilePhase & { kind: 'uploading' | 'inspected' }
  onRemove: () => void
}

/**
 * Built like Studio's `SelectedFileView`, which `import-modal` does not export; with the file's type, its numbers and its refusal.
 */
export const FileCard = ({ phase, onRemove }: FileCardProps): React.JSX.Element => {
  const { t, i18n } = useTranslation()
  const { styles } = useStyles()

  const refused = phase.kind === 'inspected' && phase.inspected.refusal !== undefined && phase.inspected.refusal !== null

  const detail = (): React.ReactNode => {
    if (phase.kind === 'uploading') {
      const percent = phase.total > 0 ? Math.round((phase.loaded * 100) / phase.total) : 0
      const { loaded, total } = formatProgress(phase.loaded, phase.total)

      return (
        <span className={ styles.progress }>
          <Progress
            aria-label={ t('data-importer.ad-hoc-import.file.title') }
            percent={ percent }
            showInfo={ false }
            size="small"
            // not green while the server still reads the file
            status="normal"
          />
          <span className={ styles.detail }>
            { t('data-importer.ad-hoc-import.file.progress', { percent, loaded, total }) }
          </span>
        </span>
      )
    }

    const { inspected } = phase
    const rows = inspected.rowCount.toLocaleString(i18n.language)
    const size = formatSize(phase.file.size)

    let text = size
    if (inspected.refusal === 'too-many-rows') {
      text = t('data-importer.ad-hoc-import.file.summary-refused', { rows, size })
    } else if (!refused) {
      text = t('data-importer.ad-hoc-import.file.summary', { rows, columns: inspected.columns.length, size })
    }

    return <span className={ styles.detail }>{ text }</span>
  }

  return (
    <div className={ cn(styles.card, { [styles.cardRefused]: refused }) }>
      <span
        aria-hidden
        className={ cn(styles.badge, { [styles.badgeRefused]: refused }) }
      >
        { fileBadge(phase) }
      </span>
      <div className={ styles.text }>
        <span className={ styles.name }>{ phase.file.name }</span>
        { detail() }
      </div>
      <IconButton
        aria-label={ t(phase.kind === 'uploading' ? 'data-importer.ad-hoc-import.file.cancel-upload' : 'data-importer.ad-hoc-import.file.remove') }
        icon={ { value: 'close' } }
        onClick={ onRemove }
        type="link"
        variant="minimal"
      />
    </div>
  )
}
