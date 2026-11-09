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
import { useStyles as usePreviewStyles } from '../preview-step/preview-step.styles'
import { FailuresTable, Notice } from './failures-table'
import { importedCount, type ImportRun } from './import-state'
import { useStyles } from './import-step.styles'

// nothing is merged yet: the rows that cannot be saved, for the user to choose how to go on
export const Decision = ({ run, waitingFile }: { run: ImportRun, waitingFile?: string }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: previewStyles } = usePreviewStyles()

  return (
    <div>
      { waitingFile !== undefined && (
        <Alert
          className={ styles.notSaved }
          message={ t('data-importer.ad-hoc-import.decision.waiting', {
            file: waitingFile,
            interpolation: { escapeValue: false }
          }) }
          showIcon
          type="info"
        />
      ) }
      <Notice run={ run } />
      <Alert
        className={ styles.notSaved }
        description={ t('data-importer.ad-hoc-import.decision.nothing-merged') }
        message={ t('data-importer.ad-hoc-import.decision.cannot-be-saved', { count: run.failed }) }
        showIcon
        type="error"
      />
      <div className={ previewStyles.counts }>
        <div className={ previewStyles.count }>
          <span className={ previewStyles.number }>{ importedCount(run) }</span>
          <span className={ previewStyles.label }>{ t('data-importer.ad-hoc-import.decision.count.ready') }</span>
        </div>
        <div className={ `${previewStyles.count} ${styles.failedCount}` }>
          <span className={ previewStyles.number }>{ run.failed }</span>
          <span>{ t('data-importer.ad-hoc-import.decision.count.cannot-be-saved') }</span>
        </div>
        <div className={ previewStyles.count }>
          <span className={ previewStyles.number }>{ run.skipped }</span>
          <span className={ previewStyles.label }>{ t('data-importer.ad-hoc-import.result.count.skipped') }</span>
        </div>
      </div>
      <FailuresTable
        run={ run }
        why={ t('data-importer.ad-hoc-import.decision.column.why') }
      />
    </div>
  )
}
