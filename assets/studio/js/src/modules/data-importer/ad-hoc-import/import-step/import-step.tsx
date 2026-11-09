/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { type ColumnDef } from '@tanstack/react-table'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Card, Grid, Progress } from '@pimcore/studio-ui-bundle/components'
import { useElementHelper } from '@pimcore/studio-ui-bundle/modules/element'
import { useStyles as usePreviewStyles } from '../preview-step/preview-step.styles'
import { importedCount, isDone, percentOf, skippedRowsUrl, type ImportRun, type ImportRunFailure } from './import-state'
import { useStyles } from './import-step.styles'

const CARD_PADDING = { x: 'extra-large', y: 'large' } as const

export interface ImportStepProps {
  fileId: string
  // null until the server has answered that the run started
  run: ImportRun | undefined
  // how many rows the import takes, known from the preview while the first answer is on its way
  total: number
}

const Running = ({ run, total }: { run: ImportRun | undefined, total: number }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const count = run?.total ?? total
  const done = run?.processed ?? 0
  const progress = t('data-importer.ad-hoc-import.run.count-of', { done, total: count })

  return (
    <Card
      className={ styles.card }
      contentPadding={ CARD_PADDING }
    >
      <div className={ styles.header }>
        <span className={ styles.title }>{ t('data-importer.ad-hoc-import.run.importing', { count }) }</span>
        <span className={ styles.muted }>{ progress }</span>
      </div>
      <Progress
        aria-label={ t('data-importer.ad-hoc-import.step.import') }
        percent={ run === undefined ? 0 : percentOf(run) }
        showInfo={ false }
      />
      <div className={ styles.step }>
        <span>{ t('data-importer.ad-hoc-import.run.saving') }</span>
        <span className={ styles.muted }>{ progress }</span>
      </div>
      <div className={ styles.hint }>{ t('data-importer.ad-hoc-import.run.background') }</div>
    </Card>
  )
}

const Complete = ({ fileId, run }: { fileId: string, run: ImportRun }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { openElement } = useElementHelper()

  return (
    <div>
      <Card
        className={ styles.result }
        contentPadding={ CARD_PADDING }
      >
        <div className={ styles.resultContent }>
          <div>
            <div className={ styles.complete }>{ t('data-importer.ad-hoc-import.result.complete') }</div>
            <div className={ styles.imported }>{ importedCount(run) }</div>
            <div>
              { t('data-importer.ad-hoc-import.result.objects-from', {
                file: run.fileName,
                interpolation: { escapeValue: false }
              }) }
            </div>
            <div className={ styles.muted }>
              { t('data-importer.ad-hoc-import.result.saved-as', {
                user: run.userName,
                time: run.finishedAt === null ? '' : new Date(run.finishedAt * 1000).toLocaleString(),
                interpolation: { escapeValue: false }
              }) }
            </div>
          </div>
          <div className={ styles.lines }>
            <div className={ styles.line }>
              <span className={ styles.lineLabel }>{ t('data-importer.ad-hoc-import.result.updated') }</span>
              <span className={ styles.lineCount }>{ run.updated }</span>
            </div>
            <div className={ styles.line }>
              <span className={ styles.lineLabel }>
                { t('data-importer.ad-hoc-import.result.created-in', {
                  folder: run.newObjectsFolder,
                  interpolation: { escapeValue: false }
                }) }
              </span>
              <span className={ styles.lineCount }>{ run.created }</span>
              <Button
                onClick={ () => { void openElement({ id: run.newObjectsFolderId, type: 'data-object' }) } }
                type="link"
              >
                { t('data-importer.ad-hoc-import.result.open-folder') }
              </Button>
            </div>
            { run.skipped > 0 && (
              <div className={ styles.line }>
                <span className={ styles.lineLabel }>{ t('data-importer.ad-hoc-import.result.skipped') }</span>
                <span className={ styles.lineCount }>{ run.skipped }</span>
                <Button
                  href={ skippedRowsUrl(fileId, run.errorMode) }
                  type="link"
                >
                  { t('data-importer.ad-hoc-import.result.download-rows') }
                </Button>
              </div>
            ) }
          </div>
        </div>
      </Card>
      <div className={ styles.footnote }>{ t('data-importer.ad-hoc-import.result.history') }</div>
    </div>
  )
}

const WithFailures = ({ run }: { run: ImportRun }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: previewStyles } = usePreviewStyles()

  const columns = useMemo<Array<ColumnDef<ImportRunFailure>>>(() => [
    {
      id: 'row',
      header: t('data-importer.ad-hoc-import.result.column.row'),
      accessorFn: (failure) => String(failure.row),
      size: 64
    },
    {
      id: 'match',
      header: run.matchColumn,
      accessorFn: (failure) => failure.matchValue
    },
    {
      id: 'action',
      header: t('data-importer.ad-hoc-import.result.column.action'),
      accessorFn: (failure) => failure.action === null ? '' : t(`data-importer.ad-hoc-import.preview.action.${failure.action}`)
    },
    {
      id: 'why',
      header: t('data-importer.ad-hoc-import.result.column.why'),
      accessorFn: (failure) => failure.message,
      cell: ({ getValue }) => <span className={ styles.error }>{ getValue<string>() }</span>
    }
  ], [run.matchColumn, styles.error, t])

  return (
    <div>
      <Alert
        className={ styles.notSaved }
        description={ t('data-importer.ad-hoc-import.result.others-saved') }
        message={ t('data-importer.ad-hoc-import.result.not-saved', { count: run.failed }) }
        showIcon
        type="error"
      />
      <div className={ previewStyles.counts }>
        <div className={ previewStyles.count }>
          <span className={ previewStyles.number }>{ run.updated }</span>
          <span className={ previewStyles.label }>{ t('data-importer.ad-hoc-import.result.count.updated') }</span>
        </div>
        <div className={ previewStyles.count }>
          <span className={ previewStyles.number }>{ run.created }</span>
          <span className={ previewStyles.label }>
            { t('data-importer.ad-hoc-import.result.count.created', {
              folder: run.newObjectsFolder,
              interpolation: { escapeValue: false }
            }) }
          </span>
        </div>
        <div className={ `${previewStyles.count} ${styles.failedCount}` }>
          <span className={ previewStyles.number }>{ run.failed }</span>
          <span>{ t('data-importer.ad-hoc-import.result.count.not-saved') }</span>
        </div>
        <div className={ previewStyles.count }>
          <span className={ previewStyles.number }>{ run.skipped }</span>
          <span className={ previewStyles.label }>{ t('data-importer.ad-hoc-import.result.count.skipped') }</span>
        </div>
      </div>
      <Grid
        autoWidth
        columns={ columns }
        data={ run.failures }
        setRowId={ (failure: ImportRunFailure) => String(failure.row) }
      />
    </div>
  )
}

export const ImportStep = ({ fileId, run, total }: ImportStepProps): React.JSX.Element => {
  if (!isDone(run)) {
    return (
      <Running
        run={ run }
        total={ total }
      />
    )
  }

  return run.failed > 0
    ? <WithFailures run={ run } />
    : (
      <Complete
        fileId={ fileId }
        run={ run }
      />
      )
}
