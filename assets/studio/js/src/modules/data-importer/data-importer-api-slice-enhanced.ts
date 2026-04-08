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
    // Classification store helpers are read-only and should never trigger unrelated refetches
    bundleDataImporterClassificationstoreLoadAttributes: {
      providesTags: []
    },
    bundleDataImporterClassificationstoreLoadKeyName: {
      providesTags: []
    },
    bundleDataImporterClassificationstoreLoadKeys: {
      providesTags: []
    },

    // Execution actions are handled optimistically in UI; no global invalidation
    bundleDataImporterConfigCancelExecution: {
      invalidatesTags: []
    },
    bundleDataImporterConfigCheckImportProgress: {
      providesTags: []
    },
    bundleDataImporterConfigCalculateTransformationResultType: {
      providesTags: []
    },

    // Preview endpoints are transient operations and must not invalidate caches
    bundleDataImporterConfigCopyPreview: {
      invalidatesTags: []
    },
    bundleDataImporterConfigLoadColumnHeaders: {
      providesTags: []
    },
    bundleDataImporterConfigLoadPreview: {
      providesTags: []
    },
    bundleDataImporterConfigLoadTransformationResult: {
      providesTags: []
    },

    bundleDataImporterConfigGet: {
      // Keep detail endpoint out of tag invalidation flow.
      // Saving should refresh list views, but must not auto-refetch this detail query.
      providesTags: []
    },

    // Saving a config should only refresh config lists, not detail/preview/helper endpoints
    bundleDataImporterConfigSave: {
      invalidatesTags: ['DataHubConfigs']
    },

    // Upload/import file helpers are explicitly refetched by caller where needed
    bundleDataImporterConfigHasImportFileUploaded: {
      providesTags: []
    },
    bundleDataImporterConfigUploadImportFile: {
      invalidatesTags: []
    },
    bundleDataImporterConfigUploadPreview: {
      invalidatesTags: []
    },

    // Start import is handled in UI with optimistic status updates and polling
    bundleDataImporterConfigStartImport: {
      invalidatesTags: []
    },

    // Static helper collections should not participate in invalidation
    bundleDataImporterConnectionList: {
      providesTags: []
    },
    bundleDataImporterDataTypeLoadClassAttributes: {
      providesTags: []
    },
    bundleDataImporterDataTypeLoadUnitData: {
      providesTags: []
    },
    bundleDataImporterUtilityCheckCrontab: {
      providesTags: []
    }
  }
})

export const {
  useBundleDataImporterConfigGetQuery,
  useBundleDataImporterConfigSaveMutation,
  useBundleDataImporterConfigHasImportFileUploadedQuery,
  useBundleDataImporterConfigCheckImportProgressQuery,
  useBundleDataImporterConfigStartImportMutation,
  useBundleDataImporterConfigCancelExecutionMutation,
  useBundleDataImporterConnectionListQuery
} = api

export type * from './data-importer-api-slice.gen'
