/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createContext, useContext } from 'react'
import type {
  BundleDataImporterCalculateTransformationResultTypeParameters,
  BundleDataImporterColumnHeadersResponse,
  BundleDataImporterCopyPreviewParameters,
  BundleDataImporterDataPreviewResponse,
  BundleDataImporterLoadPreviewParameters,
  BundleDataImporterTransformationResultPreviewsResponse,
  BundleDataImporterTransformationResultTypeResponse
} from '../../../../data-importer-api-slice.gen'
import { type BackendConfiguration } from '../../../../utils/transformers'

export interface MappingSourceQuery<T> {
  data?: T
  isLoading: boolean
  isFetching: boolean
  isError: boolean
  isSuccess: boolean
  error?: unknown
  refetch: () => Promise<unknown>
}

export interface MappingSourceQueryOptions {
  skip?: boolean
  refetchOnMountOrArgChange?: boolean
}

export type ColumnHeadersRequest = BundleDataImporterCopyPreviewParameters
export type PreviewRequest = BundleDataImporterLoadPreviewParameters
export type TransformationResultTypeRequest = BundleDataImporterCalculateTransformationResultTypeParameters
export type TransformationResultRequest = BundleDataImporterLoadPreviewParameters

/**
 * Where the mapping step reads its source data from: column headers, preview records and the
 * transformation previews. A step reads one kind of source for its whole lifetime, since the
 * source's query hooks are called in its render.
 */
export interface MappingSource {
  // names the source in debug output
  id: string
  // the configuration the step starts from
  configuration: BackendConfiguration | undefined
  isConfigurationLoaded: boolean
  // changes whenever the configuration is loaded again
  revision: string | undefined
  useColumnHeadersQuery: (
    request: ColumnHeadersRequest | undefined,
    options?: MappingSourceQueryOptions
  ) => MappingSourceQuery<BundleDataImporterColumnHeadersResponse>
  usePreviewQuery: (
    request: PreviewRequest | undefined,
    options?: MappingSourceQueryOptions
  ) => MappingSourceQuery<BundleDataImporterDataPreviewResponse>
  useTransformationResultTypeQuery: (
    request: TransformationResultTypeRequest | undefined,
    options?: MappingSourceQueryOptions
  ) => MappingSourceQuery<BundleDataImporterTransformationResultTypeResponse>
  useTransformationResultQuery: (
    request: TransformationResultRequest | undefined,
    options?: MappingSourceQueryOptions
  ) => MappingSourceQuery<BundleDataImporterTransformationResultPreviewsResponse>
}

export const MappingSourceContext = createContext<MappingSource | undefined>(undefined)

export function useMappingSource (): MappingSource {
  const source = useContext(MappingSourceContext)
  if (source === undefined) {
    throw new Error('The mapping step needs a mapping source around it')
  }

  return source
}
