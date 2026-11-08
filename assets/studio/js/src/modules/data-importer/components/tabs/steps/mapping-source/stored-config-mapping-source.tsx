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
import { useBundleDataImporterConfigGetQuery } from '../../../../data-importer-api-slice-enhanced'
import {
  useBundleDataImporterConfigCalculateTransformationResultTypeQuery,
  useBundleDataImporterConfigLoadColumnHeadersQuery,
  useBundleDataImporterConfigLoadPreviewQuery,
  useBundleDataImporterConfigLoadTransformationResultQuery
} from '../../../../data-importer-api-slice.gen'
import { type BackendConfiguration } from '../../../../utils/transformers'
import { usePreviewContext, usePreviewScope } from '../../../preview-scope'
import { type MappingSource, MappingSourceContext } from './mapping-source'

type StoredConfigQueries = Pick<MappingSource, 'useColumnHeadersQuery' | 'usePreviewQuery' | 'useTransformationResultTypeQuery' | 'useTransformationResultQuery'>

interface PreviewScopeParameters {
  previewScope?: string
  previewContext?: string
}

export const storedConfigQueries = (name: string, scope?: PreviewScopeParameters): StoredConfigQueries => {
  // under a review every request reads the change set's preview data, not the live configuration's
  const scoped = <T extends object>(parameters: T): T => ({
    ...parameters,
    ...(scope?.previewScope !== undefined && { previewScope: scope.previewScope }),
    ...(scope?.previewContext !== undefined && { previewContext: scope.previewContext })
  })

  return {
    useColumnHeadersQuery: (request, options) => useBundleDataImporterConfigLoadColumnHeadersQuery(
      { name, bundleDataImporterCopyPreviewParameters: scoped(request ?? {}) },
      { ...options, skip: options?.skip === true || request === undefined }
    ),
    usePreviewQuery: (request, options) => useBundleDataImporterConfigLoadPreviewQuery(
      { name, bundleDataImporterLoadPreviewParameters: scoped(request ?? {}) },
      { ...options, skip: options?.skip === true || request === undefined }
    ),
    useTransformationResultTypeQuery: (request, options) => useBundleDataImporterConfigCalculateTransformationResultTypeQuery(
      { name, bundleDataImporterCalculateTransformationResultTypeParameters: scoped(request ?? { currentConfig: {} }) },
      { ...options, skip: options?.skip === true || request === undefined }
    ),
    useTransformationResultQuery: (request, options) => useBundleDataImporterConfigLoadTransformationResultQuery(
      { name, bundleDataImporterLoadPreviewParameters: scoped(request ?? {}) },
      { ...options, skip: options?.skip === true || request === undefined }
    )
  }
}

export interface StoredConfigMappingSourceProps {
  configName: string
  children: React.ReactNode
}

/**
 * The mapping source of a stored import configuration: its uploaded preview file, read through
 * the configuration's endpoints.
 */
export const StoredConfigMappingSource = ({ configName, children }: StoredConfigMappingSourceProps): React.JSX.Element => {
  const { data, isSuccess, requestId } = useBundleDataImporterConfigGetQuery({ name: configName })
  const previewScope = usePreviewScope()
  const previewContext = usePreviewContext()
  const queries = useMemo(
    () => storedConfigQueries(configName, { previewScope, previewContext }),
    [configName, previewScope, previewContext]
  )
  const configuration = data?.configuration as BackendConfiguration | undefined

  const source = useMemo((): MappingSource => ({
    id: configName,
    configuration,
    isConfigurationLoaded: isSuccess,
    revision: requestId,
    ...queries
  }), [configName, configuration, isSuccess, requestId, queries])

  return (
    <MappingSourceContext.Provider value={ source }>
      { children }
    </MappingSourceContext.Provider>
  )
}
