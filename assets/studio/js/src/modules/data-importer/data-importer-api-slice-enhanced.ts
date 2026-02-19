/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { api as baseApi } from './data-importer-api-slice.gen'

export const api = baseApi.enhanceEndpoints({
  addTagTypes: ['DataHubConfigs'],
  endpoints: {
    bundleDataImporterConfigGet: {
      providesTags: []
    },
    bundleDataImporterConfigSave: {
      invalidatesTags: ['DataHubConfigs']
    }
  }
})

export const {
  useBundleDataImporterConfigGetQuery,
  useBundleDataImporterConfigSaveMutation,
  useBundleDataImporterConfigHasImportFileUploadedQuery,
  useBundleDataImporterConnectionListQuery
} = api

export type * from './data-importer-api-slice.gen'
