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
import { createColumnHelper, type ColumnDef } from '@tanstack/react-table'
import { Dropdown, DropdownButton, Box, Flex, Grid, IconButton, SearchInput, Form } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { isNotFoundError, withApiMessage } from '../../../../utils/api-error-message'
import { useBundleDataImporterConfigGetQuery } from '../../../../data-importer-api-slice-enhanced'
import { transformFormToBackend, type BackendConfiguration } from '../../../../utils/transformers'
import { type DataImporterFormValues } from '../../../../types'
import { StepHeading } from '../step-heading/step-heading'
import { usePreviewRecordQuery } from '../shared/use-preview-record-query'
import { usePreviewDataActions } from '../shared/use-preview-data-actions'
import { ConfigProvider } from 'antd'

export interface PreviewImportStepProps {
  configName: string
  isActive: boolean
  /** Called when the preview data changes (copy from source / upload). */
  onPreviewDataChange?: () => void
}

interface PreviewRow {
  dataIndex?: string
  label?: string
  data?: any
  mapped?: boolean
}

const columnHelper = createColumnHelper<PreviewRow>()

export const PreviewImportStep = ({ configName, isActive, onPreviewDataChange }: PreviewImportStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const form = Form.useFormInstance()

  const [recordIndex, setRecordIndex] = useState(0)
  const [searchText, setSearchText] = useState('')

  const { data: configData } = useBundleDataImporterConfigGetQuery({ name: configName })

  const getBackendConfig = useCallback((): BackendConfiguration => {
    const formValues = form.getFieldsValue(true) as DataImporterFormValues

    const existingConfig = (configData?.configuration ?? {}) as BackendConfiguration
    return transformFormToBackend(formValues, existingConfig)
  }, [form, configData])

  const {
    dataPreview,
    currentRecordIndex,
    isLoading: isLoadingPreview,
    isFetching: isFetchingPreview,
    isError: isPreviewError,
    error: previewError,
    load: fetchPreview
  } = usePreviewRecordQuery({
    enabled: isActive,
    getCurrentConfig: getBackendConfig
  })

  useEffect(() => {
    setRecordIndex(currentRecordIndex)
  }, [currentRecordIndex])

  useEffect(() => {
    if (previewError === undefined || previewError === null) return
    if (isNotFoundError(previewError)) return
    trackError(new ApiError(withApiMessage(previewError)))
  }, [previewError])

  const { upload, copyFromSource, isCopying, uploadModal } = usePreviewDataActions({
    configName,
    getCurrentConfig: getBackendConfig,
    onPreviewDataChange: () => {
      fetchPreview(0, { forceRefetch: true })
      onPreviewDataChange?.()
    }
  })

  const handlePrev = (): void => {
    const newIndex = Math.max(0, recordIndex - 1)
    fetchPreview(newIndex)
  }

  const handleNext = (): void => {
    fetchPreview(recordIndex + 1)
  }

  const dropdownItems = [
    {
      key: 'upload',
      label: t('data-importer.preview-import.upload-file'),
      onClick: upload
    },
    {
      key: 'copy',
      label: t('data-importer.preview-import.copy-from-source'),
      onClick: () => {
        void copyFromSource()
      }
    }
  ]

  const columns = useMemo<Array<ColumnDef<PreviewRow>>>(
    () => [
      columnHelper.accessor('label', {
        header: t('data-importer.preview-import.column.label'),
        size: 300
      }) as ColumnDef<PreviewRow>,
      columnHelper.accessor('data', {
        header: t('data-importer.preview-import.column.data'),
        meta: {
          autoWidth: true
        }
      }) as ColumnDef<PreviewRow>
    ],
    [t]
  )

  const previewData = useMemo<PreviewRow[]>(() => {
    if (isPreviewError) return []

    return dataPreview.map((row) => ({
      ...row,
      data: typeof row.data === 'string' ? row.data : JSON.stringify(row.data)
    }))
  }, [dataPreview, isPreviewError])

  const filteredData = useMemo(() => {
    if (searchText === '') return previewData
    const lower = searchText.toLowerCase()
    return previewData.filter((row) =>
      (row.label ?? '').toLowerCase().includes(lower)
    )
  }, [previewData, searchText])

  const isWorking = (isLoadingPreview || isFetchingPreview) || isCopying

  return (
    <>
      <Box margin={ { bottom: 'extra-small' } }>
        <Flex
          align="center"
          gap="extra-small"
          justify="space-between"
        >
          <Flex
            align="center"
            gap="extra-small"
          >
            <StepHeading>{ t('data-importer.preview-import.title') }</StepHeading>

            { /* provisioning and paging preview data reads the source, never the configuration:
                 they stay live where the form is read-only, so a reviewer can look at the data */ }
            <ConfigProvider componentDisabled={ false }>
              <Dropdown menu={ { items: dropdownItems } }>
                <DropdownButton
                  disabled={ isWorking }
                  type="default"
                >
                  { t('data-importer.preview-import.choose-preview-data') }
                </DropdownButton>
              </Dropdown>

              <IconButton
                disabled={ isWorking || recordIndex <= 0 }
                icon={ { value: 'chevron-left' } }
                onClick={ handlePrev }
                tooltip={ { title: t('data-importer.preview-import.prev') } }
                type="default"
              />

              <IconButton
                disabled={ isWorking }
                icon={ { value: 'chevron-right' } }
                onClick={ handleNext }
                tooltip={ { title: t('data-importer.preview-import.next') } }
                type="default"
              />
            </ConfigProvider>
          </Flex>

          <SearchInput
            maxWidth={ 320 }
            onChange={ (e) => {
              setSearchText(e.target.value)
            } }
            placeholder={ t('data-importer.preview-import.search-placeholder') }
            withClear
            withPrefix
          />
        </Flex>
      </Box>

      <Grid
        autoWidth
        columns={ columns }
        data={ filteredData }
        isLoading={ isWorking }
      />

      { uploadModal }
    </>
  )
}
