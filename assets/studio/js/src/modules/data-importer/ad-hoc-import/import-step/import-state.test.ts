/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { failedRowsUrl, importedCount, isDone, percentOf, skippedRowsUrl, type ImportRun } from './import-state'

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
