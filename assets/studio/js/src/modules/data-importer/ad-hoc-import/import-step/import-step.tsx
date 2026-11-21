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
import { Alert, Button, Card, Icon, Progress } from '@pimcore/studio-ui-bundle/components'
import { useElementHelper } from '@pimcore/studio-ui-bundle/modules/element'
import { useStyles as usePreviewStyles } from '../preview-step/preview-step.styles'
import { BranchRunning } from './branch-running'
import { Decision } from './decision'
import { FailuresTable, Notice, SummaryLine } from './failures-table'
import { importedCount, isDeciding, isDone, percentOf, skippedRowsUrl, type ImportRun, type ObjectsKind } from './import-state'
import { ResultBar } from './result-bar'
import { StepLine } from './step-line'
import { useStyles } from './import-step.styles'

const CARD_PADDING = { x: 'extra-large', y: 'large' } as const

export interface ImportStepProps {
  fileId: string
  // null until the server has answered that the run started
  run: ImportRun | undefined
  // how many rows the import takes, known from the preview while the first answer is on its way
  total: number
  // the file of an import the dialog was opened again for, which waits for the user's decision
  waitingFile?: string
  // opens the objects the import saved in a list of their own
  onShowList?: (kind: ObjectsKind) => void
}

const Running = ({ run, total }: { run: ImportRun | undefined, total: number }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  if (run?.throughBranch === true) {
    return <BranchRunning run={ run } />
  }
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
        strokeColor={ styles.barColor }
      />
      <StepLine
        detail={ progress }
        label={ t('data-importer.ad-hoc-import.run.saving') }
        state="active"
      />
      <div className={ styles.hint }>{ t('data-importer.ad-hoc-import.run.background') }</div>
    </Card>
  )
}

interface CompleteProps {
  fileId: string
  run: ImportRun
  onShowList?: (kind: ObjectsKind) => void
}

interface ResultLineProps {
  dot: string
  label: string
  count: number
  action?: React.ReactNode
}

const ResultLine = ({ dot, label, count, action }: ResultLineProps): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <div className={ styles.line }>
      <span className={ `${styles.dot} ${dot}` } />
      <span>{ label }</span>
      <span className={ styles.lineCount }>{ count }</span>
      <span className={ styles.lineAction }>{ action }</span>
    </div>
  )
}

const Complete = ({ fileId, run, onShowList }: CompleteProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { openElement } = useElementHelper()

  return (
    <div>
      <Card
        className={ styles.result }
        contentPadding="none"
      >
        <ResultBar run={ run } />
        <div className={ styles.resultContent }>
          <div className={ styles.summary }>
            <div className={ styles.complete }>
              <Icon value="check-circle" />
              { t('data-importer.ad-hoc-import.result.complete') }
            </div>
            <div className={ styles.imported }>{ importedCount(run) }</div>
            <div className={ styles.objectsFrom }>
              { t('data-importer.ad-hoc-import.result.objects-from', {
                file: run.fileName,
                interpolation: { escapeValue: false }
              }) }
            </div>
            <div className={ styles.muted }>
              { t(run.throughBranch ? 'data-importer.ad-hoc-import.result.merged-as' : 'data-importer.ad-hoc-import.result.saved-as', {
                user: run.userName,
                time: run.finishedAt === null ? '' : new Date(run.finishedAt * 1000).toLocaleString(),
                interpolation: { escapeValue: false }
              }) }
            </div>
          </div>
          <div className={ styles.lines }>
            <ResultLine
              action={ onShowList !== undefined && run.updated > 0 && (
                <Button
                  onClick={ () => { onShowList('updated') } }
                  type="link"
                >
                  { t('data-importer.ad-hoc-import.result.show-list') }
                </Button>
              ) }
              count={ run.updated }
              dot={ styles.dotUpdated }
              label={ t('data-importer.ad-hoc-import.result.updated') }
            />
            <ResultLine
              action={ (
                <Button
                  onClick={ () => { void openElement({ id: run.newObjectsFolderId, type: 'data-object' }) } }
                  type="link"
                >
                  { t('data-importer.ad-hoc-import.result.open-folder') }
                </Button>
              ) }
              count={ run.created }
              dot={ styles.dotCreated }
              label={ t('data-importer.ad-hoc-import.result.created-in', {
                folder: run.newObjectsFolder,
                interpolation: { escapeValue: false }
              }) }
            />
            { run.skipped > 0 && (
              <ResultLine
                action={ (
                  <Button
                    href={ skippedRowsUrl(fileId, run.errorMode) }
                    type="link"
                  >
                    { t('data-importer.ad-hoc-import.result.download-rows') }
                  </Button>
                ) }
                count={ run.skipped }
                dot={ styles.dotSkipped }
                label={ t('data-importer.ad-hoc-import.result.skipped') }
              />
            ) }
          </div>
        </div>
        <div className={ styles.resultFootnote }>{ t('data-importer.ad-hoc-import.result.history') }</div>
      </Card>
    </div>
  )
}

const WithFailures = ({ run }: { run: ImportRun }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: previewStyles } = usePreviewStyles()

  return (
    <div>
      <Notice run={ run } />
      <Alert
        className={ styles.notSaved }
        message={ <SummaryLine
          lead={ t('data-importer.ad-hoc-import.result.not-saved', { count: run.failed }) }
          rest={ t('data-importer.ad-hoc-import.result.others-saved') }
                  /> }
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
      <FailuresTable
        run={ run }
        why={ t('data-importer.ad-hoc-import.result.column.why') }
      />
    </div>
  )
}

export const ImportStep = ({ fileId, run, total, waitingFile, onShowList }: ImportStepProps): React.JSX.Element => {
  if (run !== undefined && isDeciding(run)) {
    return (
      <Decision
        run={ run }
        waitingFile={ waitingFile }
      />
    )
  }

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
        onShowList={ onShowList }
        run={ run }
      />
      )
}
