/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import {
  type BundleDataImporterColumnHeadersResponse,
  type BundleDataImporterDataPreviewResponse,
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery,
  useBundleDataImporterMappingLoadTransformationResultQuery
} from '../../../../data-importer-api-slice.gen'
import { type BackendConfiguration } from '../../../../utils/transformers'
import { type MappingSource, MappingSourceContext, type MappingSourceQuery } from './mapping-source'

export interface PostedMappingSourceColumn {
  dataIndex: string
  label?: string
}

export type PostedMappingSourceRecord = Record<string, unknown>

export interface PostedMappingSourceProps {
  // names the source in debug output
  id: string
  // the resolver and mapping the step starts from
  configuration: BackendConfiguration
  // changes when the configuration is loaded again
  revision?: string
  columns: PostedMappingSourceColumn[]
  // the source records the previews run on; record navigation stays within them
  records: PostedMappingSourceRecord[]
  children: React.ReactNode
}

const settled = <T, >(data: T | undefined): MappingSourceQuery<T> => ({
  data,
  isLoading: false,
  isFetching: false,
  isError: false,
  isSuccess: data !== undefined,
  refetch: async () => undefined
})

const recordAt = (records: PostedMappingSourceRecord[], recordNumber: number | undefined): { index: number, record: PostedMappingSourceRecord } => {
  const index = Math.max(0, Math.min(recordNumber ?? 0, records.length - 1))

  return { index, record: records[index] ?? {} }
}

const columnHeadersOf = (columns: PostedMappingSourceColumn[]): BundleDataImporterColumnHeadersResponse => ({
  columnHeaders: columns.map(column => ({ id: column.dataIndex, dataIndex: column.dataIndex, label: column.label ?? column.dataIndex }))
})

const previewOf = (
  columns: PostedMappingSourceColumn[],
  records: PostedMappingSourceRecord[],
  recordNumber: number | undefined
): BundleDataImporterDataPreviewResponse => {
  const { index, record } = recordAt(records, recordNumber)

  return {
    previewRecordIndex: index,
    dataPreview: columns.map(column => ({ dataIndex: column.dataIndex, label: column.label ?? column.dataIndex, data: record[column.dataIndex] === undefined ? '' : record[column.dataIndex] }))
  }
}

/**
 * A mapping source for a configuration that is not stored: the caller hands in the columns and
 * the records, and the transformation previews run on what is posted.
 */
export const PostedMappingSource = ({ id, configuration, revision, columns, records, children }: PostedMappingSourceProps): React.JSX.Element => {
  const columnHeaders = useMemo(() => columnHeadersOf(columns), [columns])

  // a new source whenever the columns or records change, so the steps reading it render again
  const source = useMemo((): MappingSource => ({
    id,
    configuration,
    isConfigurationLoaded: true,
    revision,
    useColumnHeadersQuery: (request, options) => settled(options?.skip === true || request === undefined ? undefined : columnHeaders),
    usePreviewQuery: (request, options) => {
      const recordNumber = request?.recordNumber
      // a new source keeps its hook slots, so the records belong in the deps
      const data = useMemo(() => previewOf(columns, records, recordNumber), [recordNumber, records, columns])

      return settled(options?.skip === true || request === undefined ? undefined : data)
    },
    useTransformationResultTypeQuery: (request, options) => useBundleDataImporterMappingCalculateTransformationResultTypeQuery(
      { bundleDataImporterCalculateTransformationResultTypeParameters: request ?? { currentConfig: {} } },
      { ...options, skip: options?.skip === true || request === undefined }
    ),
    useTransformationResultQuery: (request, options) => {
      const mappingConfig = (request?.currentConfig?.mappingConfig ?? configuration.mappingConfig ?? []) as Array<Record<string, unknown>>
      const { record } = recordAt(records, request?.recordNumber)

      return useBundleDataImporterMappingLoadTransformationResultQuery(
        { bundleDataImporterTransformationResultParameters: { mappingConfig, dataRow: record } },
        { ...options, skip: options?.skip === true || request === undefined }
      )
    }
  }), [id, configuration, revision, columns, records, columnHeaders])

  return (
    <MappingSourceContext.Provider value={ source }>
      { children }
    </MappingSourceContext.Provider>
  )
}
