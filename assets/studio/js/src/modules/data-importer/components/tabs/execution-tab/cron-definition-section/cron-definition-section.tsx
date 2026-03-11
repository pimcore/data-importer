/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Button,
  Flex,
  Form,
  Icon,
  Input
} from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { LoadingOutlined } from '@ant-design/icons'
import { useBundleDataImporterUtilityCheckCrontabQuery } from '../../../../data-importer-api-slice.gen'
import debounce from 'lodash/debounce'

const CRON_DEBOUNCE_MS = 500

export interface CronDefinitionSectionProps {}

/**
 * Cron definition form field with debounced server-side validation.
 * Uses antd Form.Item rules so that form.validateFields() blocks save when invalid.
 * While the result is in-flight a spinner is shown as input suffix — no red border.
 */
export const CronDefinitionSection = (_props: CronDefinitionSectionProps): React.JSX.Element => {
  const { t } = useTranslation()
  const form = Form.useFormInstance()
  const rawCronValue = (Form.useWatch(['executionConfig', 'cronDefinition']) as string | undefined) ?? ''
  const loaderType = Form.useWatch(['loaderConfig', 'type']) as string | undefined

  const [debouncedCron, setDebouncedCron] = useState(rawCronValue)
  const [isDebouncing, setIsDebouncing] = useState(false)

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const applyDebounced = useCallback(
    debounce((value: string) => {
      setDebouncedCron(value)
      setIsDebouncing(false)
    }, CRON_DEBOUNCE_MS),
    []
  )

  // Clean up debounce timer on unmount
  useEffect(() => () => { applyDebounced.cancel() }, [applyDebounced])

  // Apply debounce whenever raw value changes
  useEffect(() => {
    if (rawCronValue.trim().length > 0) {
      setIsDebouncing(true)
    }
    applyDebounced(rawCronValue)
  }, [rawCronValue, applyDebounced])

  const skipValidation = debouncedCron.trim().length === 0

  const { data: cronValidation, isFetching } = useBundleDataImporterUtilityCheckCrontabQuery(
    { cronExpression: debouncedCron },
    { skip: skipValidation }
  )

  // True while a result is still pending (debounce timer running or API call in flight)
  const isValidating = (isDebouncing || isFetching) && rawCronValue.trim().length > 0

  // Re-trigger antd field validation whenever the server result arrives,
  // so the rules-based validator reflects the latest state immediately.
  useEffect(() => {
    if (!isDebouncing && !isFetching) {
      void form.validateFields([['executionConfig', 'cronDefinition']], { dirty: false })
        .catch(() => { /* validation errors are expected and shown by antd */ })
    }
  }, [cronValidation, isFetching, isDebouncing, skipValidation])

  // rules-based validator: antd calls this on change and on form.validateFields().
  // Returns reject to block save when in-flight; reject with error message when invalid.
  const { t: tValidator } = useTranslation()
  const cronValidator = useMemo(() => ({
    validator (_: unknown, value: string): Promise<void> {
      if (!value || value.trim().length === 0) {
        return Promise.resolve()
      }
      if (isDebouncing || isFetching || cronValidation === undefined) {
        // Block save silently — the spinner suffix communicates the pending state
        return Promise.reject(new Error(''))
      }
      if (cronValidation.isValid === false) {
        return Promise.reject(new Error(cronValidation.message))
      }
      return Promise.resolve()
    }
  }), [isDebouncing, isFetching, cronValidation, tValidator])

  const handleBlur = (): void => {
    applyDebounced.flush()
  }

  // Derive the Form.Item validateStatus:
  //   - while validating: "" (neutral — no red border, no green tick)
  //   - after result: let antd handle it normally (undefined = antd-controlled)
  const validateStatus = isValidating ? ('' as const) : undefined

  return (
    <Form.Item
      hasFeedback={ !isValidating }
      label={
        <Flex
          align="center"
          gap={ 8 }
        >
          <span>{ t('data-importer.execution.cron-definition') }</span>
          <Button
            href="https://crontab.guru/"
            icon={ <Icon value="share-nodes" /> }
            rel="noopener noreferrer"
            target="_blank"
            type="link"
          >
            { t('data-importer.execution.cron-generator') }
          </Button>
        </Flex>
      }
      name={ ['executionConfig', 'cronDefinition'] }
      rules={ [cronValidator] }
      validateStatus={ validateStatus }
    >
      <Input
        disabled={ loaderType === 'push' }
        onBlur={ handleBlur }
        placeholder="0 2 * * *"
        suffix={ isValidating ? <LoadingOutlined spin /> : <span /> }
      />
    </Form.Item>
  )
}
