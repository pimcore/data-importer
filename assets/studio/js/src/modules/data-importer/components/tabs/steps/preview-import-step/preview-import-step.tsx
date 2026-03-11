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
import { Dropdown, DropdownButton, Box, Flex, Grid, IconButton, ImportModal, SearchInput, Form } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { getPrefix } from '@pimcore/studio-ui-bundle/api'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterConfigCopyPreviewMutation
} from '../../../../data-importer-api-slice.gen'
import { useBundleDataImporterConfigGetQuery } from '../../../../data-importer-api-slice-enhanced'
import { transformFormToBackend, type BackendConfiguration } from '../../../../utils/transformers'
import { type DataImporterFormValues } from '../../../../types'
import { StepHeading } from '../step-heading/step-heading'
import { usePreviewRecordQuery } from '../shared/use-preview-record-query'

export interface PreviewImportStepProps {
  configName: string
  isActive: boolean
}

interface PreviewRow {
  dataIndex?: string
  label?: string
  data?: any
  mapped?: boolean
}

const isNotFoundError = (error: unknown): boolean => {
  if (typeof error !== 'object' || error === null) return false
  return (error as { status?: unknown }).status === 404
}

const columnHelper = createColumnHelper<PreviewRow>()

export const PreviewImportStep = ({ configName, isActive }: PreviewImportStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const form = Form.useFormInstance()

  const [recordIndex, setRecordIndex] = useState(0)
  const [searchText, setSearchText] = useState('')
  const [uploadModalOpen, setUploadModalOpen] = useState(false)

  const { data: configData } = useBundleDataImporterConfigGetQuery({ name: configName })

  const getBackendConfig = useCallback((): BackendConfiguration => {
    const formValues = form.getFieldsValue(true) as DataImporterFormValues

    const existingConfig = (configData?.configuration ?? {}) as BackendConfiguration
    return transformFormToBackend(formValues, existingConfig)
  }, [form, configData])

  const [copyPreview, { isLoading: isCopying, error: copyPreviewError }] = useBundleDataImporterConfigCopyPreviewMutation()
  const {
    dataPreview,
    currentRecordIndex,
    isLoading: isLoadingPreview,
    isFetching: isFetchingPreview,
    isError: isPreviewError,
    error: previewError,
    load: fetchPreview
  } = usePreviewRecordQuery({
    configName,
    enabled: isActive,
    getCurrentConfig: getBackendConfig
  })

  useEffect(() => {
    setRecordIndex(currentRecordIndex)
  }, [currentRecordIndex])

  useEffect(() => {
    if (previewError === undefined) return
    if (isNotFoundError(previewError)) return
    trackError(new ApiError(previewError))
  }, [previewError])

  useEffect(() => {
    if (copyPreviewError === undefined) return
    trackError(new ApiError(copyPreviewError))
  }, [copyPreviewError])

  const handlePrev = (): void => {
    const newIndex = Math.max(0, recordIndex - 1)
    fetchPreview(newIndex)
  }

  const handleNext = (): void => {
    fetchPreview(recordIndex + 1)
  }

  const handleCopyFromSource = async (): Promise<void> => {
    const result = await copyPreview({
      name: configName,
      bundleDataImporterCopyPreviewParameters: {
        currentConfig: getBackendConfig()
      }
    })

    if ('error' in result) {
      return
    }

    fetchPreview(0, { forceRefetch: true })
  }

  const dropdownItems = [
    {
      key: 'upload',
      label: t('data-importer.preview-import.upload-file'),
      onClick: () => {
        setUploadModalOpen(true)
      }
    },
    {
      key: 'copy',
      label: t('data-importer.preview-import.copy-from-source'),
      onClick: () => {
        void handleCopyFromSource()
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

      <ImportModal
        action={ `${getPrefix()}/bundle/data-importer/config/${configName}/upload-preview` }
        onOpenChange={ setUploadModalOpen }
        onUploadSuccess={ () => {
          setUploadModalOpen(false)
          fetchPreview(0, { forceRefetch: true })
        } }
        open={ uploadModalOpen }
        title={ t('data-importer.preview-import.upload-file') }
      />
    </>
  )
}
