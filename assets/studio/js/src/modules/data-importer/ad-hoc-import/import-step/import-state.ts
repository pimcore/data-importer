/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { getPrefix } from '@pimcore/studio-ui-bundle/api'
import { type BundleDataImporterAdHocImportRunResponse } from '../../data-importer-api-slice.gen'
import { type ErrorMode } from '../preview-step/preview-state'

export type ImportRun = BundleDataImporterAdHocImportRunResponse
export type ImportRunFailure = ImportRun['failures'][number]

const fileUrl = (fileId: string): string => `${getPrefix()}/bundle/data-importer/ad-hoc-import/file/${fileId}`

export const isDone = (run: ImportRun | undefined): run is ImportRun => run?.state === 'done'

// rows cannot be saved and nothing is merged: the user chooses how to go on
export const isDeciding = (run: ImportRun | undefined): boolean => run?.state === 'decision'

// nothing goes on by itself any more, so there is nothing to ask the server about
export const isSettled = (run: ImportRun | undefined): boolean => isDone(run) || isDeciding(run)

const percent = (done: number, total: number): number => total === 0 ? 100 : Math.floor((done / total) * 100)

// what the bar shows: the running step's share, all of it once nothing is left, however many rows failed; through a
// branch, from the check on the merge's, so it is never full while the branch is still checked or merged
export const percentOf = (run: ImportRun): number => run.state === 'merging'
  ? percent(run.mergeDone, run.mergeTotal)
  : percent(run.processed, run.total)

// a time as the result shows it: the time alone for today, else a short date, never seconds
export const shortTimeOf = (timestamp: number | null, today: (time: string) => string, now: Date = new Date()): string => {
  if (timestamp === null) {
    return ''
  }
  const date = new Date(timestamp * 1000)
  if (date.toDateString() === now.toDateString()) {
    return today(date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }))
  }

  return date.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })
}

export const importedCount = (run: ImportRun): number => run.updated + run.created

export type BranchStep = 'written' | 'checking' | 'merging'

// the merge reports only what it has written, so checking is all it does until then
export const branchStepOf = (run: ImportRun): BranchStep => {
  if (run.state !== 'merging') {
    return 'written'
  }

  return run.mergeDone > 0 ? 'merging' : 'checking'
}

export const failedRowsUrl = (fileId: string): string => `${fileUrl(fileId)}/failed-rows`

export type ObjectsKind = 'updated' | 'created'

export const objectsExportUrl = (fileId: string, kind: ObjectsKind): string => `${fileUrl(fileId)}/run/objects/export?kind=${kind}`

export const skippedRowsUrl = (fileId: string, errorMode: ErrorMode): string => `${fileUrl(fileId)}/skipped-rows?errorMode=${errorMode}`
