/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type BundleDataImporterAdHocImportRunResponse } from '../../data-importer-api-slice.gen'
import { type ErrorMode } from '../preview-step/preview-state'

export type ImportRun = BundleDataImporterAdHocImportRunResponse
export type ImportRunFailure = ImportRun['failures'][number]

const API_BASE = '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file'

export const isDone = (run: ImportRun | undefined): run is ImportRun => run?.state === 'done'

// what the bar shows: all of it once nothing is left, however many rows failed
export const percentOf = (run: ImportRun): number => run.total === 0 ? 100 : Math.floor((run.processed / run.total) * 100)

export const importedCount = (run: ImportRun): number => run.updated + run.created

export const failedRowsUrl = (fileId: string): string => `${API_BASE}/${fileId}/failed-rows`

export const skippedRowsUrl = (fileId: string, errorMode: ErrorMode): string => `${API_BASE}/${fileId}/skipped-rows?errorMode=${errorMode}`
