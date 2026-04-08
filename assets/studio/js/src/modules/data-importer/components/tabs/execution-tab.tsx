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
import {
  DatePicker,
  Form,
  Select
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import type { DataImporterFormValues } from '../../types'
import { DataImporterPanel } from './steps/data-importer-panel/data-importer-panel'
import { CronDefinitionSection } from './execution-tab/cron-definition-section/cron-definition-section'
import { ExecutionStatus } from './execution-tab/execution-status/execution-status'

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

  const scheduleTypeOptions = [
    { value: 'recurring', label: t('data-importer.execution.schedule-type.recurring') },
    { value: 'job', label: t('data-importer.execution.schedule-type.job') }
  ]

  return (
    <>
      { /* ── Manual Execution + Execution Status (isolated to avoid poll-driven re-renders) ── */ }
      <ExecutionStatus
        configName={ configName }
        isDirty={ isDirty }
      />

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
              outputType="dateString"
              showTime={ { format: 'HH:mm' } }
            />
          </Form.Item>
        </Form.Conditional>
      </DataImporterPanel>
    </>
  )
}
