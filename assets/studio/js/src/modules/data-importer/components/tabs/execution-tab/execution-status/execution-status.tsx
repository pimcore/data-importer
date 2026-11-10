/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useState } from 'react'
import {
  Button,
  Progress,
  Text,
  Tooltip,
  useMessage
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterConfigStartImportMutation,
  useBundleDataImporterConfigCancelExecutionMutation,
  useBundleDataImporterConfigCheckImportProgressQuery
} from '../../../../data-importer-api-slice-enhanced'
import { DataImporterPanel } from '../../steps/data-importer-panel/data-importer-panel'
import { ManualExecutionButton } from '../manual-execution-button/manual-execution-button'
import { useConfigCapabilities } from '../../../config-capabilities-context'
import { useStyles } from '../../execution-tab.styles'

const POLL_INTERVAL_MS = 5000

export interface ExecutionStatusProps {
  configName: string
  isDirty: boolean
}

/**
 * Encapsulates import polling, optimistic state, the manual-execution button,
 * and the execution-status progress bar.
 *
 * Extracted from ExecutionTab so that poll-driven re-renders are isolated
 * and do not reset the DatePicker's unconfirmed selection.
 */
export const ExecutionStatus = ({ configName, isDirty }: ExecutionStatusProps): React.JSX.Element => {
  const { t } = useTranslation()
  const messageApi = useMessage()
  const { styles } = useStyles()
  const { canRunImport } = useConfigCapabilities()

  const [startImport, { isLoading: isStarting }] = useBundleDataImporterConfigStartImportMutation()
  const [cancelExecution, { isLoading: isCancelling }] = useBundleDataImporterConfigCancelExecutionMutation()

  const { data: progressData, refetch: refetchProgress } = useBundleDataImporterConfigCheckImportProgressQuery(
    { name: configName },
    { pollingInterval: POLL_INTERVAL_MS }
  )

  const [optimisticRunning, setOptimisticRunning] = useState(false)
  const [optimisticProgress, setOptimisticProgress] = useState<{ processedItems: number, totalItems: number, progress: number } | null>(null)
  const [optimisticCancelled, setOptimisticCancelled] = useState(false)
  const [hasCompleted, setHasCompleted] = useState(false)

  // Once a real "running" response arrives, clear start-optimistic flags
  useEffect(() => {
    if (progressData?.isRunning === true) {
      setOptimisticRunning(false)
      setOptimisticProgress(null)
      setHasCompleted(false)
    }
  }, [progressData?.isRunning])

  // Once polling confirms not running, clear cancel-optimistic flag; mark completed if items were processed
  useEffect(() => {
    if (progressData?.isRunning === false) {
      setOptimisticCancelled(false)
      if ((progressData.processedItems ?? 0) > 0) {
        setHasCompleted(true)
      }
    }
  }, [progressData?.isRunning])

  const isRunning = !optimisticCancelled && (optimisticRunning || (progressData?.isRunning ?? false))
  const progress = optimisticProgress?.progress ?? progressData?.progress ?? 0
  const processedItems = optimisticProgress?.processedItems ?? progressData?.processedItems ?? 0
  const totalItems = optimisticProgress?.totalItems ?? progressData?.totalItems ?? 0

  const handleStartImport = async (): Promise<void> => {
    const result = await startImport({ name: configName })

    if ('error' in result) {
      if (result.error !== undefined) {
        trackError(new ApiError(result.error))
      }
      void messageApi.error(t('data-importer.execution.start-import.error'))
      return
    }

    if (result.data.success) {
      void messageApi.success(t('data-importer.execution.start-import.success'))
      // Immediately show progress bar at 0, resetting any previous run's values
      setOptimisticProgress({ processedItems: 0, totalItems: progressData?.totalItems ?? 0, progress: 0 })
      setOptimisticRunning(true)
      setHasCompleted(false)
    } else {
      void messageApi.error(t('data-importer.execution.start-import.error'))
    }
    void refetchProgress()
  }

  const handleCancelExecution = async (): Promise<void> => {
    const result = await cancelExecution({ name: configName })

    if ('error' in result) {
      if (result.error !== undefined) {
        trackError(new ApiError(result.error))
      }
      void messageApi.error(t('data-importer.execution.cancel.error'))
      return
    }

    void messageApi.success(t('data-importer.execution.cancel.success'))
    // Immediately hide the progress bar before polling confirms
    setOptimisticCancelled(true)
    setOptimisticRunning(false)
    setOptimisticProgress(null)
    setHasCompleted(false)
    void refetchProgress()
  }

  return (
    <>
      { /* ── Manual Execution ── */ }
      <DataImporterPanel title={ t('data-importer.execution.manual-execution') }>
        <ManualExecutionButton
          canRunImport={ canRunImport }
          isDirty={ isDirty }
          isStarting={ isStarting }
          label={ t('data-importer.execution.start-import') }
          onStart={ () => { void handleStartImport() } }
        />
      </DataImporterPanel>

      { /* ── Execution Status ── */ }
      <DataImporterPanel
        noWidthLimit
        title={ t('data-importer.execution.status.title') }
      >
        { isRunning
          ? (
            <>
              <p className={ styles.progressLabel }>
                { t('data-importer.execution.status.current-progress') }
              </p>
              <div className={ styles.progressWrapper }>
                <Progress
                  format={ () => t('data-importer.execution.status.processing', { processedItems, totalItems }) }
                  percent={ Math.round(progress * 100) }
                  percentPosition={ { align: 'start', type: 'inner' } }
                  size={ [-1, 32] }
                  status="active"
                  strokeColor={ styles.colorFill }
                  trailColor={ 'rgba(0, 0, 0, 0.06)' }
                />
              </div>
              { /* Cancelling a run is an execution action, so `disabled` is passed
                   explicitly instead of inheriting the read-only form state. */ }
              <Tooltip title={ canRunImport ? undefined : t('data-hub.config.no-update-permission') }>
                { /* span needed so Tooltip works on a disabled button */ }
                <span>
                  <Button
                    disabled={ !canRunImport }
                    loading={ isCancelling }
                    onClick={ () => { void handleCancelExecution() } }
                  >
                    { t('data-importer.execution.status.cancel') }
                  </Button>
                </span>
              </Tooltip>
            </>
            )
          : (
            <Text>
              { t(hasCompleted
                ? 'data-importer.execution.status.finished'
                : 'data-importer.execution.status.not-running') }
            </Text>
            ) }
      </DataImporterPanel>
    </>
  )
}
