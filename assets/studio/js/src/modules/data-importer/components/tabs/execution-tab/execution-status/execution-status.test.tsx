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
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { type BundleDataImporterImportProgressResponse } from '../../../../data-importer-api-slice.gen'
import { ExecutionStatus } from './execution-status'

const NOT_RUNNING: BundleDataImporterImportProgressResponse = {
  isRunning: false,
  totalItems: 0,
  processedItems: 0,
  progress: 0,
  stoppedAfterError: false
}

// the progress query result; like RTK Query, a new result re-renders the component
let progress = NOT_RUNNING
const progressListeners = new Set<() => void>()
const subscribeToProgress = (listener: () => void): (() => void) => {
  progressListeners.add(listener)
  return () => progressListeners.delete(listener)
}

// what the import has become by the time Studio asks for its progress after starting it
let progressAfterStart = NOT_RUNNING

const refetch = jest.fn(async () => {
  progress = progressAfterStart
  progressListeners.forEach(listener => { listener() })
  return await Promise.resolve({ data: progress, startedTimeStamp: Date.now() })
})
const startImport = jest.fn(async () => await Promise.resolve({ data: { success: true } }))

jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Button: ({ children, onClick }: { children?: React.ReactNode, onClick?: () => void }) => (
    <button onClick={ onClick }>{ children }</button>
  ),
  Progress: ({ format }: { format?: () => React.ReactNode }) => <div>{ format?.() }</div>,
  Text: ({ children }: { children?: React.ReactNode }) => <span>{ children }</span>,
  Tooltip: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  useMessage: () => ({ success: jest.fn(), error: jest.fn() })
}))

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({ t: (key: string) => key })
}))

jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: jest.fn(),
  trackError: jest.fn()
}))

jest.mock('../../../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterConfigStartImportMutation: () => [startImport, { isLoading: false }],
  useBundleDataImporterConfigCancelExecutionMutation: () => [jest.fn(), { isLoading: false }],
  useBundleDataImporterConfigCheckImportProgressQuery: () => ({
    data: React.useSyncExternalStore(subscribeToProgress, () => progress),
    refetch
  })
}))

jest.mock('../../steps/data-importer-panel/data-importer-panel', () => ({
  DataImporterPanel: ({ children }: { children?: React.ReactNode }) => <section>{ children }</section>
}))

jest.mock('../manual-execution-button/manual-execution-button', () => ({
  ManualExecutionButton: ({ label, onStart }: { label: string, onStart: () => void }) => (
    <button onClick={ onStart }>{ label }</button>
  )
}))

jest.mock('../../../config-capabilities-context', () => ({
  useConfigCapabilities: () => ({ canRunImport: true })
}))

jest.mock('../../execution-tab.styles', () => ({
  useStyles: () => ({ styles: {} })
}))

const startAndWaitFor = async (statusKey: string): Promise<void> => {
  fireEvent.click(screen.getByText('data-importer.execution.start-import'))
  await waitFor(() => { expect(refetch).toHaveBeenCalled() })
  expect(await screen.findByText(statusKey)).toBeTruthy()
  expect(screen.queryByText('data-importer.execution.status.processing')).toBeNull()
}

describe('ExecutionStatus', () => {
  beforeEach(() => {
    progress = NOT_RUNNING
    refetch.mockClear()
  })

  afterEach(cleanup)

  it('shows the stop of an import that stopped before any poll saw it running', async () => {
    progressAfterStart = { ...NOT_RUNNING, totalItems: 4, stoppedAfterError: true }
    render(<ExecutionStatus
      configName="feed"
      isDirty={ false }
           />)

    await startAndWaitFor('data-importer.execution.status.stopped')
  })

  it('shows the stop again when the next run stops just as quickly', async () => {
    progress = { ...NOT_RUNNING, totalItems: 4, stoppedAfterError: true }
    progressAfterStart = progress
    render(<ExecutionStatus
      configName="feed"
      isDirty={ false }
           />)

    await startAndWaitFor('data-importer.execution.status.stopped')
  })

  it('shows an import as finished when it was done before any poll saw it running', async () => {
    progressAfterStart = { ...NOT_RUNNING, totalItems: 4, processedItems: 4, progress: 1 }
    render(<ExecutionStatus
      configName="feed"
      isDirty={ false }
           />)

    await startAndWaitFor('data-importer.execution.status.finished')
  })

  it('does not take the answer to a poll sent before the start for the new run', async () => {
    progress = { ...NOT_RUNNING, totalItems: 4, stoppedAfterError: true }
    progressAfterStart = { ...NOT_RUNNING, isRunning: true, totalItems: 4 }
    // RTK Query answers a refetch with the poll still running, which was sent before the start
    refetch.mockImplementationOnce(async () => await Promise.resolve({ data: progress, startedTimeStamp: Date.now() - 5000 }))
    render(<ExecutionStatus
      configName="feed"
      isDirty={ false }
           />)

    fireEvent.click(screen.getByText('data-importer.execution.start-import'))
    await waitFor(() => { expect(refetch).toHaveBeenCalledTimes(2) })
    expect(await screen.findByText('data-importer.execution.status.processing')).toBeTruthy()
    expect(screen.queryByText('data-importer.execution.status.stopped')).toBeNull()
  })
})
