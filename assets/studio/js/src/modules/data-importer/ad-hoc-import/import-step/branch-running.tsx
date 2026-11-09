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
import { Card, Icon, Progress } from '@pimcore/studio-ui-bundle/components'
import { branchStepOf, percentOf, type ImportRun } from './import-state'
import { useStyles } from './import-step.styles'

const CARD_PADDING = { x: 'extra-large', y: 'large' } as const

type StepState = 'done' | 'active' | 'pending'

interface StepLineProps {
  label: string
  state: StepState
  detail?: string
}

const StepLine = ({ label, state, detail }: StepLineProps): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <div
      aria-current={ state === 'active' ? 'step' : undefined }
      className={ styles.branchStep }
    >
      { state === 'done' && (
        <span className={ styles.branchStepDone }>
          <Icon value="check-circle" />
        </span>
      ) }
      { state === 'active' && <Icon value="spinner" /> }
      { state === 'pending' && <span className={ styles.branchStepPending } /> }
      <span className={ `${styles.branchStepLabel} ${state === 'active' ? styles.branchStepActive : ''}` }>{ label }</span>
      { detail !== undefined && <span className={ styles.muted }>{ detail }</span> }
    </div>
  )
}

/**
 * An import through a Change Control branch: its rows are written into the branch, which is checked, then merged.
 */
export const BranchRunning = ({ run }: { run: ImportRun }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const step = branchStepOf(run)
  const rows = t('data-importer.ad-hoc-import.run.count-of', { done: run.processed, total: run.total })
  const merged = t('data-importer.ad-hoc-import.run.count-of', { done: run.mergeDone, total: run.mergeTotal })

  return (
    <Card
      className={ styles.card }
      contentPadding={ CARD_PADDING }
    >
      <div className={ styles.header }>
        <span className={ styles.title }>{ t('data-importer.ad-hoc-import.run.importing', { count: run.total }) }</span>
        <span className={ styles.muted }>{ step === 'written' ? rows : merged }</span>
      </div>
      <Progress
        aria-label={ t('data-importer.ad-hoc-import.step.import') }
        percent={ percentOf(run) }
        showInfo={ false }
      />
      <StepLine
        detail={ step === 'written' ? rows : t('data-importer.ad-hoc-import.run.branch.objects', { count: run.mergeTotal }) }
        label={ t('data-importer.ad-hoc-import.run.branch.written') }
        state={ step === 'written' ? 'active' : 'done' }
      />
      <StepLine
        label={ t('data-importer.ad-hoc-import.run.branch.checking') }
        state={ { written: 'pending', checking: 'active', merging: 'done' }[step] as StepState }
      />
      <StepLine
        detail={ step === 'merging' ? merged : undefined }
        label={ t('data-importer.ad-hoc-import.run.branch.merging', {
          user: run.userName,
          interpolation: { escapeValue: false }
        }) }
        state={ step === 'merging' ? 'active' : 'pending' }
      />
      <div className={ styles.hint }>{ t('data-importer.ad-hoc-import.run.background') }</div>
    </Card>
  )
}
