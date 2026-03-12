/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect } from 'react'
import { Flex, Select, Form, Switch } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useBundleDataImporterConfigGetQuery } from '../../../../data-importer-api-slice-enhanced'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { StepHeading } from '../step-heading/step-heading'
import { filterByLabel } from '../select-utils'
import type { DataImporterFormValues } from '../../../../types'
import { useStyles } from './processing-settings-step.styles'

export interface ProcessingSettingsStepProps {
  configName: string
}

export const ProcessingSettingsStep = ({ configName }: ProcessingSettingsStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles, cx } = useStyles()
  const form = Form.useFormInstance()

  const { data: configData } = useBundleDataImporterConfigGetQuery({ name: configName })

  const columnHeaderOptions = (configData?.columnHeaders ?? []).map((header) => {
    // API returns objects {id, dataIndex, label}; type says string[] — handle both
    const h = header as unknown as { dataIndex?: string, label?: string } | string
    const value = typeof h === 'string' ? h : (h.dataIndex ?? '')
    const label = typeof h === 'string' ? h : (h.label ?? h.dataIndex ?? '')
    return { value, label }
  })

  const executionTypeOptions = [
    { value: 'sequential', label: t('data-importer.processing.execution-type.sequential') },
    { value: 'parallel', label: t('data-importer.processing.execution-type.parallel') }
  ]

  const cleanupStrategyOptions = [
    { value: 'delete', label: t('data-importer.processing.cleanup.strategy.delete') },
    { value: 'unpublish', label: t('data-importer.processing.cleanup.strategy.unpublish') }
  ]

  const hasIdDataIndex = (values: DataImporterFormValues): boolean =>
    Boolean(values.processingConfig?.idDataIndex)

  // Watch parent logging toggles to auto-check sub-items when parent is enabled
  const disableInfoLogs = Form.useWatch(['processingConfig', 'logging', 'disableInfoLogs']) as boolean | undefined
  const disableErrorLogs = Form.useWatch(['processingConfig', 'logging', 'disableErrorLogs']) as boolean | undefined

  useEffect(() => {
    if (disableInfoLogs === true) {
      form.setFieldValue(['processingConfig', 'logging', 'disableInfoFileObjects'], true)
    }
  }, [disableInfoLogs, form])

  useEffect(() => {
    if (disableErrorLogs === true) {
      form.setFieldValue(['processingConfig', 'logging', 'disableErrorFileObjects'], true)
    }
  }, [disableErrorLogs, form])

  return (
    <>
      <StepHeading>{ t('data-importer.processing.title') }</StepHeading>

      { /* Execution */ }
      <DataImporterPanel title={ t('data-importer.processing.execution.title') }>
        <Form.Item
          label={ t('data-importer.processing.execution-type') }
          name={ ['processingConfig', 'executionType'] }
          tooltip={ t('data-importer.processing.execution-type.tooltip') }
        >
          <Select
            filterOption={ filterByLabel }
            options={ executionTypeOptions }
            showSearch
          />
        </Form.Item>

        <Form.Item
          name={ ['processingConfig', 'doArchiveImportFile'] }
          valuePropName="checked"
        >
          <Switch labelRight={ t('data-importer.processing.archive-import-file') } />
        </Form.Item>

        <Form.Item
          name={ ['processingConfig', 'disableVersioning'] }
          valuePropName="checked"
        >
          <Switch labelRight={ t('data-importer.processing.disable-versioning') } />
        </Form.Item>
      </DataImporterPanel>

      { /* ID, Delta Check & Cleanup */ }
      <DataImporterPanel title={ t('data-importer.processing.id-delta.title') }>
        <Form.Item
          label={ t('data-importer.processing.id-data-index') }
          name={ ['processingConfig', 'idDataIndex'] }
          tooltip={ t('data-importer.processing.id-data-index.tooltip') }
        >
          <Select
            allowClear
            filterOption={ filterByLabel }
            options={ columnHeaderOptions }
            placeholder={ t('data-importer.processing.id-data-index-placeholder') }
            showSearch
          />
        </Form.Item>

        <Form.Conditional condition={ (values) =>
          hasIdDataIndex(values as unknown as DataImporterFormValues)
        }
        >
          <Form.Item
            name={ ['processingConfig', 'doDeltaCheck'] }
            valuePropName="checked"
          >
            <Switch labelRight={ t('data-importer.processing.delta-check') } />
          </Form.Item>

          <Form.Item
            name={ ['processingConfig', 'cleanup', 'doCleanup'] }
            valuePropName="checked"
          >
            <Switch labelRight={ t('data-importer.processing.cleanup.do-cleanup') } />
          </Form.Item>

          <Form.Conditional condition={ (values) =>
            Boolean((values as unknown as DataImporterFormValues).processingConfig?.cleanup?.doCleanup)
          }
          >
            <DataImporterPanel
              theme="fieldset"
              title={ t('data-importer.processing.cleanup.title') }
            >
              <Form.Item
                label={ t('data-importer.processing.cleanup.strategy') }
                name={ ['processingConfig', 'cleanup', 'strategy'] }
                tooltip={ t('data-importer.processing.cleanup.strategy.tooltip') }
              >
                <Select
                  filterOption={ filterByLabel }
                  options={ cleanupStrategyOptions }
                  showSearch
                />
              </Form.Item>
            </DataImporterPanel>
          </Form.Conditional>
        </Form.Conditional>
      </DataImporterPanel>

      { /* Logging */ }
      <DataImporterPanel title={ t('data-importer.processing.logging.title') }>
        <Flex
          className={ styles.loggingGroups }
          gap="small"
          vertical
        >
          <div className={ styles.loggingGroup }>
            <div className={ styles.loggingGroupTitle }>{ t('data-importer.processing.logging.info.title') }</div>
            <Form.Item
              className={ styles.loggingItem }
              name={ ['processingConfig', 'logging', 'disableInfoLogs'] }
              valuePropName="checked"
            >
              <Switch labelRight={ t('data-importer.processing.logging.info.disable-logs') } />
            </Form.Item>

            <Form.Item
              className={ cx(styles.loggingItem, styles.loggingItemLast) }
              name={ ['processingConfig', 'logging', 'disableInfoFileObjects'] }
              valuePropName="checked"
            >
              <Switch
                disabled={ disableInfoLogs === true }
                labelRight={ t('data-importer.processing.logging.info.disable-file-objects') }
              />
            </Form.Item>
          </div>

          <div className={ styles.loggingGroup }>
            <div className={ styles.loggingGroupTitle }>{ t('data-importer.processing.logging.error.title') }</div>
            <Form.Item
              className={ styles.loggingItem }
              name={ ['processingConfig', 'logging', 'disableErrorLogs'] }
              valuePropName="checked"
            >
              <Switch labelRight={ t('data-importer.processing.logging.error.disable-logs') } />
            </Form.Item>

            <Form.Item
              className={ cx(styles.loggingItem, styles.loggingItemLast) }
              name={ ['processingConfig', 'logging', 'disableErrorFileObjects'] }
              valuePropName="checked"
            >
              <Switch
                disabled={ disableErrorLogs === true }
                labelRight={ t('data-importer.processing.logging.error.disable-file-objects') }
              />
            </Form.Item>
          </div>
        </Flex>
      </DataImporterPanel>
    </>
  )
}
