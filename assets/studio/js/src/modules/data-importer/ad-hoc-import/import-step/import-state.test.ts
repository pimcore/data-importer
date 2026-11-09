/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { branchStepOf, failedRowsUrl, importedCount, isDeciding, isDone, isSettled, percentOf, skippedRowsUrl, type ImportRun } from './import-state'

jest.mock('@pimcore/studio-ui-bundle/api', () => ({ getPrefix: () => '/custom/api' }))

const run = (given: Partial<ImportRun>): ImportRun => ({
  state: 'running',
  total: 4,
  processed: 1,
  updated: 1,
  created: 0,
  failed: 0,
  skipped: 0,
  errorMode: 'skip',
  fileName: 'autumn.csv',
  matchColumn: 'SKU',
  newObjectsFolder: '/Products',
  newObjectsFolderId: 7,
  userName: 'Jo Doe',
  finishedAt: null,
  throughBranch: false,
  mergeTotal: 0,
  mergeDone: 0,
  notice: null,
  failures: [],
  ...given
})

describe('the state of an import', () => {
  it('is done only once the server says so', () => {
    expect(isDone(undefined)).toBe(false)
    expect(isDone(run({}))).toBe(false)
    expect(isDone(run({ state: 'done' }))).toBe(true)
  })

  it('shows how much is dealt with, whether the rows went well or not', () => {
    expect(percentOf(run({ processed: 1, total: 4 }))).toBe(25)
    expect(percentOf(run({ processed: 3, total: 3, failed: 2 }))).toBe(100)
    expect(percentOf(run({ processed: 0, total: 0 }))).toBe(100)
  })

  it('counts the objects written, not the rows left', () => {
    expect(importedCount(run({ updated: 298, created: 11, failed: 4 }))).toBe(309)
  })

  it('downloads the failed rows and the skipped rows of its file below the API prefix of the install', () => {
    expect(failedRowsUrl('id-1')).toBe('/custom/api/bundle/data-importer/ad-hoc-import/file/id-1/failed-rows')
    expect(skippedRowsUrl('id-1', 'values')).toBe('/custom/api/bundle/data-importer/ad-hoc-import/file/id-1/skipped-rows?errorMode=values')
  })
})

describe('an import through a branch', () => {
  it('settles once it ended or waits for the user to decide', () => {
    expect(isSettled(run({}))).toBe(false)
    expect(isSettled(run({ state: 'merging' }))).toBe(false)
    expect(isSettled(run({ state: 'decision' }))).toBe(true)
    expect(isSettled(run({ state: 'done' }))).toBe(true)
    expect(isSettled(undefined)).toBe(false)
    expect(isDeciding(run({ state: 'decision' }))).toBe(true)
    expect(isDeciding(run({ state: 'done' }))).toBe(false)
  })

  it('writes into the branch, then checks it, then merges', () => {
    expect(branchStepOf(run({ state: 'running' }))).toBe('written')
    expect(branchStepOf(run({ state: 'merging', mergeTotal: 3, mergeDone: 0 }))).toBe('checking')
    expect(branchStepOf(run({ state: 'merging', mergeTotal: 3, mergeDone: 1 }))).toBe('merging')
  })

  it('shows how far the merge got while it merges', () => {
    expect(percentOf(run({ state: 'merging', processed: 4, total: 4, mergeTotal: 4, mergeDone: 1 }))).toBe(25)
    expect(percentOf(run({ state: 'merging', mergeTotal: 0, mergeDone: 0 }))).toBe(100)
  })
})
