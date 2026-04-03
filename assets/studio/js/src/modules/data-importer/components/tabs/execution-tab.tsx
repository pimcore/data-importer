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
  DatePicker,
  Form,
  Progress,
  Select,
  Text,
  useMessage
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterConfigStartImportMutation,
  useBundleDataImporterConfigCancelExecutionMutation,
  useBundleDataImporterConfigCheckImportProgressQuery
} from '../../data-importer-api-slice.gen'
import type { DataImporterFormValues } from '../../types'
import { DataImporterPanel } from './steps/data-importer-panel/data-importer-panel'
import { useStyles } from './execution-tab.styles'
import { CronDefinitionSection } from './execution-tab/cron-definition-section/cron-definition-section'
import { ManualExecutionButton } from './execution-tab/manual-execution-button/manual-execution-button'

const POLL_INTERVAL_MS = 5000

export interface ExecutionTabProps {
  configName: string
  isDirty: boolean
}

const isRecurring = (values: DataImporterFormValues): boolean =>
  (values.executionConfig?.scheduleType ?? 'recurring') === 'recurring'

const isOneTimeJob = (values: DataImporterFormValues): boolean =>
  values.executionConfig?.scheduleType === 'job'

export const ExecutionTab = ({ configName, isDirty }: ExecutionTabProps): React.JSX.Element => {
  const { t } = useTranslation()
  const messageApi = useMessage()
  const { styles } = useStyles()

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

  const scheduleTypeOptions = [
    { value: 'recurring', label: t('data-importer.execution.schedule-type.recurring') },
    { value: 'job', label: t('data-importer.execution.schedule-type.job') }
  ]

  return (
    <>
      { /* ── Manual Execution ── */ }
      <DataImporterPanel title={ t('data-importer.execution.manual-execution') }>
        <ManualExecutionButton
          isDirty={ isDirty }
          isStarting={ isStarting }
          label={ t('data-importer.execution.start-import') }
          onStart={ () => { void handleStartImport() } }
        />
      </DataImporterPanel>

      { /* ── Scheduled Execution ── */ }
      <DataImporterPanel title={ t('data-importer.execution.settings.title') }>
        <Form.Item
          initialValue="recurring"
          label={ t('data-importer.execution.schedule-type') }
          name={ ['executionConfig', 'scheduleType'] }
        >
          <Select options={ scheduleTypeOptions } />
        </Form.Item>

        { /* Cron Definition (recurring — disabled when push) */ }
        <Form.Conditional condition={ (values) =>
          isRecurring(values as unknown as DataImporterFormValues)
        }
        >
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.execution.schedule-type.recurring') }
          >
            <CronDefinitionSection />
          </DataImporterPanel>
        </Form.Conditional>

        { /* Scheduled At (one-time job) */ }
        <Form.Conditional condition={ (values) =>
          isOneTimeJob(values as unknown as DataImporterFormValues)
        }
        >
          <Form.Item
            label={ t('data-importer.execution.scheduled-at') }
            name={ ['executionConfig', 'scheduledAt'] }
          >
            <DatePicker
              outputFormat="DD-MM-YYYY HH:mm"
              outputType="dateString"
              showTime={ { format: 'HH:mm' } }
            />
          </Form.Item>
        </Form.Conditional>
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
              <Button
                loading={ isCancelling }
                onClick={ () => { void handleCancelExecution() } }
              >
                { t('data-importer.execution.status.cancel') }
              </Button>
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
