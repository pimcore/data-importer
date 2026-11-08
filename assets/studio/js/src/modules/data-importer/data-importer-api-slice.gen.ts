import { api } from "@pimcore/studio-ui-bundle/api";
export const addTagTypes = ["Bundle Data Importer"] as const;
const injectedRtkApi = api
    .enhanceEndpoints({
        addTagTypes,
    })
    .injectEndpoints({
        endpoints: (build) => ({
            bundleDataImporterClassificationstoreLoadAttributes: build.query<
                BundleDataImporterClassificationstoreLoadAttributesApiResponse,
                BundleDataImporterClassificationstoreLoadAttributesApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/classificationstore/attributes`,
                    params: { classId: queryArg.classId },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterClassificationstoreLoadKeyName: build.query<
                BundleDataImporterClassificationstoreLoadKeyNameApiResponse,
                BundleDataImporterClassificationstoreLoadKeyNameApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/classificationstore/key-name`,
                    params: { keyId: queryArg.keyId },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterClassificationstoreLoadKeys: build.query<
                BundleDataImporterClassificationstoreLoadKeysApiResponse,
                BundleDataImporterClassificationstoreLoadKeysApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/classificationstore/keys`,
                    params: {
                        classId: queryArg.classId,
                        fieldName: queryArg.fieldName,
                        transformationResultType:
                            queryArg.transformationResultType,
                        sort: queryArg.sort,
                        start: queryArg.start,
                        limit: queryArg.limit,
                        searchfilter: queryArg.searchfilter,
                        filter: queryArg.filter,
                    },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigCalculateTransformationResultType:
                build.query<
                    BundleDataImporterConfigCalculateTransformationResultTypeApiResponse,
                    BundleDataImporterConfigCalculateTransformationResultTypeApiArg
                >({
                    query: (queryArg) => ({
                        url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/transformation-result-type`,
                        method: "POST",
                        body: queryArg.bundleDataImporterCalculateTransformationResultTypeParameters,
                    }),
                    providesTags: ["Bundle Data Importer"],
                }),
            bundleDataImporterMappingCalculateTransformationResultType:
                build.query<
                    BundleDataImporterMappingCalculateTransformationResultTypeApiResponse,
                    BundleDataImporterMappingCalculateTransformationResultTypeApiArg
                >({
                    query: (queryArg) => ({
                        url: `/pimcore-studio/api/bundle/data-importer/mapping/transformation-result-type`,
                        method: "POST",
                        body: queryArg.bundleDataImporterCalculateTransformationResultTypeParameters,
                    }),
                    providesTags: ["Bundle Data Importer"],
                }),
            bundleDataImporterMappingLoadTransformationResult: build.query<
                BundleDataImporterMappingLoadTransformationResultApiResponse,
                BundleDataImporterMappingLoadTransformationResultApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/mapping/transformation-result`,
                    method: "POST",
                    body: queryArg.bundleDataImporterTransformationResultParameters,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigCancelExecution: build.mutation<
                BundleDataImporterConfigCancelExecutionApiResponse,
                BundleDataImporterConfigCancelExecutionApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/cancel-execution`,
                    method: "PUT",
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigCheckImportProgress: build.query<
                BundleDataImporterConfigCheckImportProgressApiResponse,
                BundleDataImporterConfigCheckImportProgressApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/check-import-progress`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigCopyPreview: build.mutation<
                BundleDataImporterConfigCopyPreviewApiResponse,
                BundleDataImporterConfigCopyPreviewApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/copy-preview`,
                    method: "POST",
                    body: queryArg.bundleDataImporterCopyPreviewParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigGet: build.query<
                BundleDataImporterConfigGetApiResponse,
                BundleDataImporterConfigGetApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigSave: build.mutation<
                BundleDataImporterConfigSaveApiResponse,
                BundleDataImporterConfigSaveApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}`,
                    method: "PUT",
                    body: queryArg.bundleDataImporterConfigurationSaveParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigHasImportFileUploaded: build.query<
                BundleDataImporterConfigHasImportFileUploadedApiResponse,
                BundleDataImporterConfigHasImportFileUploadedApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/has-import-file-uploaded`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigLoadColumnHeaders: build.query<
                BundleDataImporterConfigLoadColumnHeadersApiResponse,
                BundleDataImporterConfigLoadColumnHeadersApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/column-headers`,
                    method: "POST",
                    body: queryArg.bundleDataImporterCopyPreviewParameters,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigLoadPreview: build.query<
                BundleDataImporterConfigLoadPreviewApiResponse,
                BundleDataImporterConfigLoadPreviewApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/load-preview`,
                    method: "POST",
                    body: queryArg.bundleDataImporterLoadPreviewParameters,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigLoadTransformationResult: build.query<
                BundleDataImporterConfigLoadTransformationResultApiResponse,
                BundleDataImporterConfigLoadTransformationResultApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/transformation-result`,
                    method: "POST",
                    body: queryArg.bundleDataImporterLoadPreviewParameters,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigStartImport: build.mutation<
                BundleDataImporterConfigStartImportApiResponse,
                BundleDataImporterConfigStartImportApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/start-import`,
                    method: "PUT",
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigUploadImportFile: build.mutation<
                BundleDataImporterConfigUploadImportFileApiResponse,
                BundleDataImporterConfigUploadImportFileApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/upload-import-file`,
                    method: "POST",
                    body: queryArg.body,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConfigUploadPreview: build.mutation<
                BundleDataImporterConfigUploadPreviewApiResponse,
                BundleDataImporterConfigUploadPreviewApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/config/${queryArg.name}/upload-preview`,
                    method: "POST",
                    body: queryArg.body,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterConnectionList: build.query<
                BundleDataImporterConnectionListApiResponse,
                BundleDataImporterConnectionListApiArg
            >({
                query: () => ({
                    url: `/pimcore-studio/api/bundle/data-importer/connection/list`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterDataTypeLoadClassAttributes: build.query<
                BundleDataImporterDataTypeLoadClassAttributesApiResponse,
                BundleDataImporterDataTypeLoadClassAttributesApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/data-type/class-attributes`,
                    params: {
                        classId: queryArg.classId,
                        loadAdvancedRelations: queryArg.loadAdvancedRelations,
                        systemRead: queryArg.systemRead,
                        systemWrite: queryArg.systemWrite,
                        transformationResultType:
                            queryArg.transformationResultType,
                    },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterDataTypeLoadUnitData: build.query<
                BundleDataImporterDataTypeLoadUnitDataApiResponse,
                BundleDataImporterDataTypeLoadUnitDataApiArg
            >({
                query: () => ({
                    url: `/pimcore-studio/api/bundle/data-importer/data-type/unit-data`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterMappingSuggestionLoadAi: build.query<
                BundleDataImporterMappingSuggestionLoadAiApiResponse,
                BundleDataImporterMappingSuggestionLoadAiApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/mapping-suggestions/ai/${queryArg.requestId}`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterMappingSuggestionSuggest: build.mutation<
                BundleDataImporterMappingSuggestionSuggestApiResponse,
                BundleDataImporterMappingSuggestionSuggestApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/mapping-suggestions`,
                    method: "POST",
                    body: queryArg.bundleDataImporterMappingSuggestionParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportListConfigurations: build.query<
                BundleDataImporterAdHocImportListConfigurationsApiResponse,
                BundleDataImporterAdHocImportListConfigurationsApiArg
            >({
                query: () => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/configurations`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterUtilityCheckCrontab: build.query<
                BundleDataImporterUtilityCheckCrontabApiResponse,
                BundleDataImporterUtilityCheckCrontabApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/utility/check-crontab`,
                    params: { cronExpression: queryArg.cronExpression },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportUploadFile: build.mutation<
                BundleDataImporterAdHocImportUploadFileApiResponse,
                BundleDataImporterAdHocImportUploadFileApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file`,
                    method: "POST",
                    body: queryArg.body,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportInspectFile: build.mutation<
                BundleDataImporterAdHocImportInspectFileApiResponse,
                BundleDataImporterAdHocImportInspectFileApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/inspect`,
                    method: "POST",
                    body: queryArg.bundleDataImporterAdHocImportFileSettings,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportDeleteFile: build.mutation<
                BundleDataImporterAdHocImportDeleteFileApiResponse,
                BundleDataImporterAdHocImportDeleteFileApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}`,
                    method: "DELETE",
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportLoadMatchFields: build.query<
                BundleDataImporterAdHocImportLoadMatchFieldsApiResponse,
                BundleDataImporterAdHocImportLoadMatchFieldsApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/match-fields`,
                    params: { classId: queryArg.classId },
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportLoadMapping: build.mutation<
                BundleDataImporterAdHocImportLoadMappingApiResponse,
                BundleDataImporterAdHocImportLoadMappingApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/mapping`,
                    method: "POST",
                    body: queryArg.bundleDataImporterMappingSuggestionParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportLoadClassificationStoreKeys:
                build.query<
                    BundleDataImporterAdHocImportLoadClassificationStoreKeysApiResponse,
                    BundleDataImporterAdHocImportLoadClassificationStoreKeysApiArg
                >({
                    query: (queryArg) => ({
                        url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/classification-store-keys`,
                        params: {
                            classId: queryArg.classId,
                            fieldName: queryArg.fieldName,
                            searchfilter: queryArg.searchfilter,
                            start: queryArg.start,
                            limit: queryArg.limit,
                        },
                    }),
                    providesTags: ["Bundle Data Importer"],
                }),
            bundleDataImporterAdHocImportPreviewTransformations: build.mutation<
                BundleDataImporterAdHocImportPreviewTransformationsApiResponse,
                BundleDataImporterAdHocImportPreviewTransformationsApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/transformations`,
                    method: "POST",
                    body: queryArg.bundleDataImporterAdHocImportTransformationParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportDryRun: build.mutation<
                BundleDataImporterAdHocImportDryRunApiResponse,
                BundleDataImporterAdHocImportDryRunApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/dry-run`,
                    method: "POST",
                    body: queryArg.bundleDataImporterAdHocImportDryRunParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportStartRun: build.mutation<
                BundleDataImporterAdHocImportStartRunApiResponse,
                BundleDataImporterAdHocImportStartRunApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/run`,
                    method: "POST",
                    body: queryArg.bundleDataImporterAdHocImportRunParameters,
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportLoadRun: build.query<
                BundleDataImporterAdHocImportLoadRunApiResponse,
                BundleDataImporterAdHocImportLoadRunApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/run`,
                }),
                providesTags: ["Bundle Data Importer"],
            }),
            bundleDataImporterAdHocImportDiscardRun: build.mutation<
                BundleDataImporterAdHocImportDiscardRunApiResponse,
                BundleDataImporterAdHocImportDiscardRunApiArg
            >({
                query: (queryArg) => ({
                    url: `/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/${queryArg.fileId}/run`,
                    method: "DELETE",
                }),
                invalidatesTags: ["Bundle Data Importer"],
            }),
        }),
        overrideExisting: false,
    });
export { injectedRtkApi as api };
export type BundleDataImporterClassificationstoreLoadAttributesApiResponse =
    /** status 200 List of classification store attributes for the class */ BundleDataImporterClassAttributesResponse;
export type BundleDataImporterClassificationstoreLoadAttributesApiArg = {
    /** The ID of the data object class to load classification store attributes for */
    classId: string;
};
export type BundleDataImporterClassificationstoreLoadKeyNameApiResponse =
    /** status 200 The resolved group name and key name, or the key ID as fallback */ BundleDataImporterClassificationStoreKeyNameResponse;
export type BundleDataImporterClassificationstoreLoadKeyNameApiArg = {
    /** The classification store key ID in group-key format (e.g., "1-5") */
    keyId: string;
};
export type BundleDataImporterClassificationstoreLoadKeysApiResponse =
    /** status 200 Paginated list of classification store key-group relations with total count */ BundleDataImporterClassificationStoreKeysResponse;
export type BundleDataImporterClassificationstoreLoadKeysApiArg = {
    /** The ID of the data object class */
    classId: string;
    /** The classification store field name */
    fieldName: string;
    /** Filter keys by transformation result data type */
    transformationResultType: string;
    /** JSON-encoded sort configuration */
    sort?: string;
    /** Pagination offset */
    start?: number;
    /** Maximum number of results */
    limit?: number;
    /** Search string to filter keys by name, group name, or description */
    searchfilter?: string;
    /** JSON-encoded column filter configuration */
    filter?: string;
};
export type BundleDataImporterConfigCalculateTransformationResultTypeApiResponse =
    /** status 200 The evaluated transformation result data type */ BundleDataImporterTransformationResultTypeResponse;
export type BundleDataImporterConfigCalculateTransformationResultTypeApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterCalculateTransformationResultTypeParameters: BundleDataImporterCalculateTransformationResultTypeParameters;
};
export type BundleDataImporterMappingCalculateTransformationResultTypeApiResponse =
    /** status 200 The evaluated transformation result data type */ BundleDataImporterTransformationResultTypeResponse;
export type BundleDataImporterMappingCalculateTransformationResultTypeApiArg = {
    bundleDataImporterCalculateTransformationResultTypeParameters: BundleDataImporterCalculateTransformationResultTypeParameters;
};
export type BundleDataImporterMappingLoadTransformationResultApiResponse =
    /** status 200 Transformation result preview strings for each mapping entry */ BundleDataImporterTransformationResultPreviewsResponse;
export type BundleDataImporterMappingLoadTransformationResultApiArg = {
    bundleDataImporterTransformationResultParameters: BundleDataImporterTransformationResultParameters;
};
export type BundleDataImporterConfigCancelExecutionApiResponse =
    /** status 200 Import execution cancelled and queue cleaned up successfully */ void;
export type BundleDataImporterConfigCancelExecutionApiArg = {
    /** Name of the configuration */
    name: string;
};
export type BundleDataImporterConfigCheckImportProgressApiResponse =
    /** status 200 Import progress status with running state and item counts */ BundleDataImporterImportProgressResponse;
export type BundleDataImporterConfigCheckImportProgressApiArg = {
    /** Name of the configuration */
    name: string;
};
export type BundleDataImporterConfigCopyPreviewApiResponse =
    /** status 200 Preview data copied successfully */ void;
export type BundleDataImporterConfigCopyPreviewApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterCopyPreviewParameters: BundleDataImporterCopyPreviewParameters;
};
export type BundleDataImporterConfigGetApiResponse =
    /** status 200 Data Importer configuration details with column headers */ BundleDataImporterConfigurationDetail;
export type BundleDataImporterConfigGetApiArg = {
    /** Name of the configuration */
    name: string;
};
export type BundleDataImporterConfigSaveApiResponse =
    /** status 200 Successfully saved configuration with updated details */ BundleDataImporterConfigurationDetail;
export type BundleDataImporterConfigSaveApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterConfigurationSaveParameters: BundleDataImporterConfigurationSaveParameters;
};
export type BundleDataImporterConfigHasImportFileUploadedApiResponse =
    /** status 200 Import file existence status with file path and translated message */ BundleDataImporterImportFileStatusResponse;
export type BundleDataImporterConfigHasImportFileUploadedApiArg = {
    /** Name of the configuration */
    name: string;
};
export type BundleDataImporterConfigLoadColumnHeadersApiResponse =
    /** status 200 Available column headers from the preview data */ BundleDataImporterColumnHeadersResponse;
export type BundleDataImporterConfigLoadColumnHeadersApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterCopyPreviewParameters: BundleDataImporterCopyPreviewParameters;
};
export type BundleDataImporterConfigLoadPreviewApiResponse =
    /** status 200 Preview data with column details and record index */ BundleDataImporterDataPreviewResponse;
export type BundleDataImporterConfigLoadPreviewApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterLoadPreviewParameters: BundleDataImporterLoadPreviewParameters;
};
export type BundleDataImporterConfigLoadTransformationResultApiResponse =
    /** status 200 Transformation result preview strings for each mapping entry */ BundleDataImporterTransformationResultPreviewsResponse;
export type BundleDataImporterConfigLoadTransformationResultApiArg = {
    /** Name of the configuration */
    name: string;
    bundleDataImporterLoadPreviewParameters: BundleDataImporterLoadPreviewParameters;
};
export type BundleDataImporterConfigStartImportApiResponse =
    /** status 200 Whether the import was successfully prepared and started */ BundleDataImporterImportStartResponse;
export type BundleDataImporterConfigStartImportApiArg = {
    /** Name of the configuration */
    name: string;
};
export type BundleDataImporterConfigUploadImportFileApiResponse =
    /** status 200 Import file uploaded successfully */ void;
export type BundleDataImporterConfigUploadImportFileApiArg = {
    /** Name of the configuration */
    name: string;
    body: {
        /** Import data file to upload */
        file: Blob;
    };
};
export type BundleDataImporterConfigUploadPreviewApiResponse =
    /** status 200 Preview data uploaded successfully */ void;
export type BundleDataImporterConfigUploadPreviewApiArg = {
    /** Name of the configuration */
    name: string;
    body: {
        /** Preview data file to upload */
        file: Blob;
    };
};
export type BundleDataImporterConnectionListApiResponse =
    /** status 200 List of available database connections with names and service identifiers */ BundleDataImporterConnectionsResponse;
export type BundleDataImporterConnectionListApiArg = void;
export type BundleDataImporterDataTypeLoadClassAttributesApiResponse =
    /** status 200 List of available data object attributes for the class */ BundleDataImporterClassAttributesResponse;
export type BundleDataImporterDataTypeLoadClassAttributesApiArg = {
    /** The ID of the data object class to load attributes for */
    classId: string;
    /** Whether to include advanced relation field types */
    loadAdvancedRelations?: boolean;
    /** Whether to include system read fields */
    systemRead?: boolean;
    /** Whether to include system write fields */
    systemWrite?: boolean;
    /** Filter attributes by transformation result data type. If not provided, defaults to DEFAULT and NUMERIC types. */
    transformationResultType?: string;
};
export type BundleDataImporterDataTypeLoadUnitDataApiResponse =
    /** status 200 List of available quantity value units with IDs and abbreviations */ BundleDataImporterUnitDataResponse;
export type BundleDataImporterDataTypeLoadUnitDataApiArg = void;
export type BundleDataImporterMappingSuggestionLoadAiApiResponse =
    /** status 200 Where the AI stage stands, with its suggestions once it finished */ BundleDataImporterMappingSuggestionsResponse;
export type BundleDataImporterMappingSuggestionLoadAiApiArg = {
    /** RequestId of the mapping suggestion request */
    requestId: string;
};
export type BundleDataImporterMappingSuggestionSuggestApiResponse =
    /** status 200 The suggested field per column, best score first */ BundleDataImporterMappingSuggestionsResponse;
export type BundleDataImporterMappingSuggestionSuggestApiArg = {
    bundleDataImporterMappingSuggestionParameters: BundleDataImporterMappingSuggestionParameters;
};
export type BundleDataImporterUtilityCheckCrontabApiResponse =
    /** status 200 Cron expression validation result with validity flag and error message */ BundleDataImporterCronValidationResponse;
export type BundleDataImporterUtilityCheckCrontabApiArg = {
    /** The cron expression to validate */
    cronExpression?: string;
};
export type BundleDataImporterAdHocImportUploadFileApiResponse =
    /** status 200 The file, its detected settings and first rows, or why it is refused */ BundleDataImporterAdHocImportFileResponse;
export type BundleDataImporterAdHocImportUploadFileApiArg = {
    body: {
        /** The CSV or XLSX file */
        file: Blob;
        /** The data object folder the import starts from */
        folderId: number;
    };
};
export type BundleDataImporterAdHocImportInspectFileApiResponse =
    /** status 200 The file under these settings, or why it is refused under them */ BundleDataImporterAdHocImportFileResponse;
export type BundleDataImporterAdHocImportInspectFileApiArg = {
    /** Name of the file */
    fileId: string;
    bundleDataImporterAdHocImportFileSettings: BundleDataImporterAdHocImportFileSettings;
};
export type BundleDataImporterAdHocImportDeleteFileApiResponse =
    /** status 200 The file was deleted */ void;
export type BundleDataImporterAdHocImportDeleteFileApiArg = {
    /** Name of the file */
    fileId: string;
};
export type BundleDataImporterAdHocImportLoadMatchFieldsApiResponse =
    /** status 200 List of the fields */ BundleDataImporterClassAttributesResponse;
export type BundleDataImporterAdHocImportLoadMatchFieldsApiArg = {
    /** The ID of the data object class */
    classId: string;
};
export type BundleDataImporterAdHocImportLoadMappingApiResponse =
    /** status 200 The fields and the suggested field per column */ BundleDataImporterAdHocImportMappingResponse;
export type BundleDataImporterAdHocImportLoadMappingApiArg = {
    bundleDataImporterMappingSuggestionParameters: BundleDataImporterMappingSuggestionParameters;
};
export type BundleDataImporterAdHocImportLoadClassificationStoreKeysApiResponse =
    /** status 200 A page of the keys and their total count */ BundleDataImporterClassificationStoreKeysResponse;
export type BundleDataImporterAdHocImportLoadClassificationStoreKeysApiArg = {
    /** The ID of the data object class */
    classId: string;
    /** The classification store field name */
    fieldName: string;
    /** Search string to filter keys by name, group name, or description */
    searchfilter?: string;
    /** Pagination offset */
    start?: number;
    /** Maximum number of results */
    limit?: number;
};
export type BundleDataImporterAdHocImportListConfigurationsApiResponse =
    /** status 200 The configurations, by name */ BundleDataImporterAdHocImportConfigurationsResponse;
export type BundleDataImporterAdHocImportListConfigurationsApiArg = void;
export type BundleDataImporterAdHocImportPreviewTransformationsApiResponse =
    /** status 200 The values after each transformation, whether the result fits the field, and the transformations that can come next */ BundleDataImporterAdHocImportTransformationResponse;
export type BundleDataImporterAdHocImportPreviewTransformationsApiArg = {
    /** FileId of the file */
    fileId: string;
    bundleDataImporterAdHocImportTransformationParameters: BundleDataImporterAdHocImportTransformationParameters;
};
export type BundleDataImporterAdHocImportStartRunApiResponse =
    /** status 200 The run, as it starts */ BundleDataImporterAdHocImportRunResponse;
export type BundleDataImporterAdHocImportStartRunApiArg = {
    /** The file */
    fileId: string;
    bundleDataImporterAdHocImportRunParameters: BundleDataImporterAdHocImportRunParameters;
};
export type BundleDataImporterAdHocImportLoadRunApiResponse =
    /** status 200 The run */ BundleDataImporterAdHocImportRunResponse;
export type BundleDataImporterAdHocImportLoadRunApiArg = {
    /** The file */
    fileId: string;
};
export type BundleDataImporterAdHocImportDiscardRunApiResponse = unknown;
export type BundleDataImporterAdHocImportDiscardRunApiArg = {
    /** The file */
    fileId: string;
};
export type BundleDataImporterAdHocImportDryRunApiResponse =
    /** status 200 Every row of the file with what its import would do */ BundleDataImporterAdHocImportDryRunResponse;
export type BundleDataImporterAdHocImportDryRunApiArg = {
    /** FileId of the file */
    fileId: string;
    bundleDataImporterAdHocImportDryRunParameters: BundleDataImporterAdHocImportDryRunParameters;
};
export type BundleDataImporterClassAttributesResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** List of available data object attributes for the class */
    attributes: object[];
};
export type Error = {
    /** Message */
    message: string;
};
export type DevError = {
    /** Message */
    message: string;
    /** Details */
    details: string;
};
export type BundleDataImporterClassificationStoreKeyNameResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** The key ID (group-key format) */
    keyId?: any;
    /** The group name */
    groupName?: any;
    /** The key name */
    keyName?: any;
};
export type BundleDataImporterClassificationStoreKeysResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** List of classification store key-group relations */
    data: {
        /** Key ID */
        keyId?: number;
        /** Group ID */
        groupId?: number;
        /** Key name */
        keyName?: string;
        /** Key description */
        keyDescription?: string;
        /** Combined group-key ID */
        id?: string;
        /** Sort order */
        sorter?: number;
        /** Group name */
        groupName?: string;
    }[];
    /** Total count of matching records */
    total: number;
};
export type BundleDataImporterTransformationResultTypeResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** The evaluated transformation result data type */
    type: string;
};
export type BundleDataImporterCalculateTransformationResultTypeParameters = {
    /** A single mapping configuration entry to evaluate the transformation result type for */
    currentConfig: {
        /** Mapping label */
        label?: string;
        /** Data source column indices */
        dataSourceIndex?: string[];
        transformationPipeline?: object[];
        dataTarget?: object;
    };
};
export type BundleDataImporterTransformationResultPreviewsResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Transformation result preview strings for each mapping entry */
    transformationResultPreviews: string[];
};
export type BundleDataImporterTransformationResultParameters = {
    /** Mapping configuration entries to preview */
    mappingConfig: {
        [key: string]: any;
    }[];
    /** The source record to transform: column => value */
    dataRow: {
        [key: string]: any;
    };
};
export type BundleDataImporterImportProgressResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Whether an import is currently running */
    isRunning: boolean;
    /** Total number of items to import */
    totalItems: number;
    /** Number of items already processed */
    processedItems: number;
    /** Progress as a ratio between 0 and 1 */
    progress: number;
};
export type BundleDataImporterCopyPreviewParameters = {
    /** Optional unsaved in-progress configuration from the UI. When provided, the loader uses these settings instead of the saved configuration. */
    currentConfig?: {
        general?: object;
        loaderConfig?: object;
        interpreterConfig?: object;
        resolverConfig?: object;
        processingConfig?: object;
        mappingConfig?: object;
        executionConfig?: object;
    };
};
export type BundleDataImporterConfigurationDetail = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Configuration name */
    name: string;
    /** Configuration data */
    configuration: {
        general?: object;
        loaderConfig?: object;
        interpreterConfig?: object;
        resolverConfig?: object;
        processingConfig?: object;
        mappingConfig?: object;
        executionConfig?: object;
    };
    /** User permissions */
    userPermissions: {
        /** Whether the user can update this configuration */
        update?: boolean;
        /** Whether the user can delete this configuration */
        delete?: boolean;
    };
    /** Modification date timestamp */
    modificationDate: number;
    /** Available column headers from preview data */
    columnHeaders?: string[];
};
export type BundleDataImporterConfigurationSaveParameters = {
    /** Configuration data */
    configuration: {
        general?: object;
        loaderConfig?: object;
        interpreterConfig?: object;
        resolverConfig?: object;
        processingConfig?: object;
        mappingConfig?: object;
        executionConfig?: object;
    };
    /** Modification date timestamp for optimistic locking */
    modificationDate: number;
};
export type BundleDataImporterImportFileStatusResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Whether the import file has been uploaded */
    exists: boolean;
    /** Status message about the import file */
    message: string;
    /** Path of the import file in storage (only when exists is true) */
    filePath?: any;
};
export type BundleDataImporterColumnHeadersResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Available column headers from the preview data */
    columnHeaders: {
        /** Column index */
        id?: string;
        /** Column data index */
        dataIndex?: string;
        /** Column label */
        label?: string;
    }[];
};
export type BundleDataImporterDataPreviewResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Preview data rows with column metadata */
    dataPreview: {
        /** Column index */
        dataIndex?: string;
        /** Column label */
        label?: string;
        /** Cell data value. May be any JSON value (string, number, boolean, array, object or null) depending on the source data type. */
        data?: any;
        /** Whether this column is mapped */
        mapped?: boolean;
    }[];
    /** The actual record index that was loaded */
    previewRecordIndex: number;
};
export type BundleDataImporterLoadPreviewParameters = {
    /** Optional unsaved in-progress configuration from the UI. When provided, the interpreter and mapping use these settings instead of the saved configuration. */
    currentConfig?: {
        general?: object;
        loaderConfig?: object;
        interpreterConfig?: object;
        resolverConfig?: object;
        processingConfig?: object;
        mappingConfig?: object;
        executionConfig?: object;
    };
    /** Zero-based record number to preview from the data source */
    recordNumber?: number;
};
export type BundleDataImporterImportStartResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Whether the import was successfully prepared and started */
    success: boolean;
};
export type BundleDataImporterConnectionsResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** List of available Doctrine database connections */
    connections: {
        /** The connection name */
        name?: string;
        /** The connection service identifier */
        value?: string;
    }[];
};
export type BundleDataImporterUnitDataResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** List of quantity value units */
    unitList: {
        /** The unit ID */
        unitId?: string;
        /** The unit abbreviation */
        abbreviation?: string;
    }[];
};
export type BundleDataImporterMappingSuggestionsResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** The suggested field per column, best score first; a column without a match has none */
    suggestions: {
        /** Column data index */
        sourceIndex: string;
        /** Column name */
        sourceLabel: string;
        /** Key of the suggested field */
        targetFieldName: string;
        /** Title of the suggested field */
        targetFieldLabel: string;
        /** Transformation result type that writes the suggested field */
        transformationResultType: string;
        /** How well the names match, from 0 to 100 */
        score: number;
        /** Language guessed from the column name, for a localized field */
        language: any;
        /** Where the suggestion comes from: the name comparison or the AI stage */
        source: "name" | "ai";
    }[];
    /** Where the AI stage stands; while it is running, load its suggestions with the request id */
    aiStatus: "none" | "running" | "finished" | "failed";
    /** The id to load the suggestions of the AI stage with; null when it started no task */
    aiRequestId: any;
};
export type BundleDataImporterMappingSuggestionParameters = {
    /** The ID of the data object class to suggest fields of */
    classId: string;
    /** The source columns to suggest a field for */
    columns: {
        /** Column data index */
        index: string;
        /** Column name */
        label: string;
    }[];
    /** A stored configuration whose preview file may give sample values for the AI stage */
    configName?: any;
    /** Its unsaved in-progress settings, if any, to read the preview file with */
    currentConfig?: {
        loaderConfig?: object;
        interpreterConfig?: object;
    };
};
export type BundleDataImporterCronValidationResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Whether the cron expression is valid */
    isValid: boolean;
    /** Error message if the cron expression is invalid, empty string otherwise */
    message: string;
};
export type BundleDataImporterAdHocImportFileResponse = {
    /** Rows a file may have */
    maxRows: number;
    /** The stored file; null if it was refused for good and not kept */
    fileId?: string | null;
    /** The file type; null if it is none of the supported ones */
    type?: ("csv" | "xlsx") | null;
    /** Why the file cannot be imported under these settings; null if it can */
    refusal?: ("too-many-rows" | "too-many-cells" | "unreadable" | "wrong-format") | null;
    /** CSV: the delimiter in use */
    delimiter?: string | null;
    /** CSV: the delimiter detected in the file */
    detectedDelimiter?: string | null;
    /** CSV: the enclosure in use */
    enclosure?: string | null;
    /** CSV: the escape character in use */
    escape?: string | null;
    /** Whether the first row holds the column names */
    hasHeaderRow: boolean;
    /** XLSX: the sheet in use */
    sheetName?: string | null;
    /** XLSX: the sheets of the file */
    sheets: string[];
    /** Rows of data */
    rowCount: number;
    /** The columns; dataIndex is the key of the column in a row of an import */
    columns: {
        dataIndex: string;
        label: string;
        /** CSV with a header row: the column's place in the file, from 0; columns keyed by position have none */
        position?: number;
    }[];
    /** The first rows, their values in the order of the columns */
    firstRows: (string | null)[][];
};
export type BundleDataImporterAdHocImportFileSettings = {
    /** CSV: the delimiter; empty for the detected one */
    delimiter?: string | null;
    /** CSV: the enclosure */
    enclosure?: string | null;
    /** CSV: the escape character */
    escape?: string | null;
    /** Whether the first row holds the column names */
    hasHeaderRow?: boolean;
    /** XLSX: the sheet; empty for the first one */
    sheetName?: string | null;
};
export type BundleDataImporterAdHocImportMappingResponse = {
    /** The fields of the class a column can be imported into */
    fields: {
        /** Key of the field */
        key: string;
        /** Title of the field */
        title: string;
        /** Whether the field holds a value per language */
        localized: boolean;
        /** Whether the class requires a value in the field */
        mandatory: boolean;
        /** Transformation result type that writes the field */
        transformationResultType: string;
        /** Whether a column goes into the field without a transformation, converted to its type */
        takesColumnAsIs: boolean;
        /** Where the field is in the class layout, outermost first, as the grid groups it */
        group: string[];
        /** Whether the field is a classification store, whose key is chosen with the field */
        classificationStore: boolean;
    }[];
    /** The suggested field per column, best score first; a column without a match has none */
    suggestions: {
        /** Column data index */
        sourceIndex: string;
        /** Column name */
        sourceLabel: string;
        /** Key of the suggested field */
        targetFieldName: string;
        /** Title of the suggested field */
        targetFieldLabel: string;
        /** Transformation result type that writes the suggested field */
        transformationResultType: string;
        /** How well the names match, from 0 to 100 */
        score: number;
        /** Language guessed from the column name, for a localized field */
        language: string | null;
    }[];
};
export type BundleDataImporterAdHocImportConfigurationsResponse = {
    /** The data object import configurations the user may read, by name */
    configurations: {
        /** Name of the configuration */
        name: string;
        /** ID of the class it imports */
        classId: string | null;
        /** Name of the class it imports */
        className: string | null;
        /** What it finds objects by: the object ID or a field */
        matchBy: "id" | "field" | null;
        /** The field it finds objects by */
        matchField: string | null;
        /** The language of a localized field it finds objects by */
        matchLanguage: string | null;
        /** The column holding the value it finds objects by */
        matchColumn: string | null;
        /** Path of the folder it creates new objects in */
        newObjectsFolder: string | null;
        /** The columns it reads */
        columns: string[];
        /** What its columns are named by: the header row's names or the position */
        columnsBy: "name" | "position";
        /** Why an ad hoc import cannot use it: class, matching, folder, folder-missing or folder-not-allowed; null if it can */
        unusable: string | null;
    }[];
};
export type BundleDataImporterAdHocImportTransformationParameters = {
    /** The ID of the data object class to import into */
    classId: string;
    /** Data index of the column */
    column: string;
    /** Key of the field the column goes into */
    field: string;
    /** The transformations of the column, in order */
    transformationPipeline: {
        /** Operator type */
        type: string;
        /** The operator's settings */
        settings?: object;
    }[];
    /** CSV: the delimiter; empty for the detected one */
    delimiter?: string | null;
    /** CSV: the enclosure */
    enclosure?: string | null;
    /** CSV: the escape character */
    escape?: string | null;
    /** Whether the first row holds the column names */
    hasHeaderRow?: boolean;
    /** XLSX: the sheet; empty for the first one */
    sheetName?: string | null;
};
export type BundleDataImporterAdHocImportTransformationCell = {
    /** The value as text, one item per entry of a list; none for an empty value */
    items: string[];
    /** Whether a step failed on this row's value */
    failed: boolean;
};
export type BundleDataImporterAdHocImportTransformationResponse = {
    /** The column's values in the first rows of the file */
    values: BundleDataImporterAdHocImportTransformationCell[];
    /** Each transformation with the values after it */
    steps: {
        /** Operator type */
        type: string;
        /** Transformation result type after the step; null once a step cannot take its input */
        resultType: string | null;
        /** Why the step cannot take the result of the steps before it */
        error: string | null;
        /** One value per row of the first rows */
        values: BundleDataImporterAdHocImportTransformationCell[];
    }[];
    /** The operator added to convert the result to the field's type; null when it fits as it is */
    conversion: string | null;
    /** Transformation result type that goes into the field; null once a step cannot take its input */
    resultType: string | null;
    /** Whether the result can go into the field */
    fits: boolean;
    /** The values that go into the field, converted */
    results: BundleDataImporterAdHocImportTransformationCell[];
    /** The operator types that can be added next and still lead to the field */
    offered: string[];
};
export type BundleDataImporterAdHocImportRunParameters = {
    settings: BundleDataImporterAdHocImportDryRunParameters;
    /** skip: leave out every row with an error; values: import a row without its invalid values */
    errorMode: "skip" | "values";
    /** The name of the file, as the result names it */
    fileName: string;
};
export type BundleDataImporterAdHocImportRunResponse = {
    /** Whether the run still goes on */
    state: "running" | "done";
    /** The rows the run imports */
    total: number;
    /** The rows it has dealt with */
    processed: number;
    updated: number;
    created: number;
    /** The rows that could not be saved */
    failed: number;
    /** The rows left out by the choice in the preview */
    skipped: number;
    errorMode: "skip" | "values";
    fileName: string;
    /** How the column holding the match value is called */
    matchColumn: string;
    newObjectsFolder: string;
    newObjectsFolderId: number;
    /** Who runs the import */
    userName: string;
    /** When the run ended, as a timestamp */
    finishedAt: number | null;
    /** The rows not saved, once the run has ended */
    failures: {
        row: number;
        matchValue: string;
        action: "update" | "create" | null;
        /** Why it was not saved */
        message: string;
    }[];
};
export type BundleDataImporterAdHocImportDryRunParameters = {
    /** The ID of the data object folder the import goes into */
    folderId: number;
    /** The ID of the data object class to import into */
    classId: string;
    /** Match existing objects by their Object ID or by a field */
    matchBy: "id" | "field";
    /** Data index of the column holding the match value */
    matchColumn: string;
    /** Key of the field to match by */
    matchField?: string | null;
    /** Language of a localized field to match by */
    matchLanguage?: string | null;
    /** Path of the folder new objects go to; empty for the folder the import goes into */
    newObjectsFolder?: string | null;
    /** The columns mapped here, each into one field; the column matched by goes into its field too */
    columns?: {
        /** Data index of the column */
        column: string;
        /** Key of the field */
        field: string;
        /** Language of a localized field */
        language?: string | null;
        /** For a classification store, the key the column goes into, as <group id>-<key id> */
        key?: string | null;
        /** The transformations of the column, in order */
        transformationPipeline?: object[];
    }[];
    /** Name of the Data Importer configuration to map with instead */
    configuration?: string | null;
    /** Per column the configuration reads, the data index of that column in the file */
    configurationColumns?: {
        [key: string]: string;
    };
    /** CSV: the delimiter; empty for the detected one */
    delimiter?: string | null;
    /** CSV: the enclosure */
    enclosure?: string | null;
    /** CSV: the escape character */
    escape?: string | null;
    /** Whether the first row holds the column names */
    hasHeaderRow?: boolean;
    /** XLSX: the sheet; empty for the first one */
    sheetName?: string | null;
};
export type BundleDataImporterAdHocImportDryRunResponse = {
    /** How the column holding the match value is called */
    matchColumn: string;
    /** Path of the folder new objects go to */
    newObjectsFolder: string;
    /** The fields the import writes and the preview shows, beside the field matched by */
    fields: {
        /** Key of the field; null for a target the preview cannot read */
        key: string | null;
        /** Language of a localized field */
        language: string | null;
        /** How the field is called */
        label: string;
    }[];
    /** Every row of the file, in its order */
    rows: {
        /** Number of the row in the file, the header row counted */
        row: number;
        /** The value matched by */
        matchValue: string;
        /** update or create; null for a row that means no object and is skipped */
        action: ("update" | "create") | null;
        /** Why the row means no object */
        error: string | null;
        /** Per field, the value before and after the import */
        values: {
            old: string | null;
            new: string | null;
        }[];
        /** The values that cannot go into their field; skipping the row or leaving the field unchanged */
        invalid: {
            /** Position of the field among fields; null for the field matched by */
            field: number | null;
            /** How the field is called */
            label: string;
            /** The value in the file */
            value: string;
            /** What is wrong */
            message: string;
        }[];
        /** A save would refuse the row even without its invalid values, so it is skipped in either error mode */
        blocked: boolean;
    }[];
};
export const {
    useBundleDataImporterClassificationstoreLoadAttributesQuery,
    useBundleDataImporterClassificationstoreLoadKeyNameQuery,
    useBundleDataImporterClassificationstoreLoadKeysQuery,
    useBundleDataImporterConfigCalculateTransformationResultTypeQuery,
    useBundleDataImporterMappingCalculateTransformationResultTypeQuery,
    useBundleDataImporterMappingLoadTransformationResultQuery,
    useBundleDataImporterConfigCancelExecutionMutation,
    useBundleDataImporterConfigCheckImportProgressQuery,
    useBundleDataImporterConfigCopyPreviewMutation,
    useBundleDataImporterConfigGetQuery,
    useBundleDataImporterConfigSaveMutation,
    useBundleDataImporterConfigHasImportFileUploadedQuery,
    useBundleDataImporterConfigLoadColumnHeadersQuery,
    useBundleDataImporterConfigLoadPreviewQuery,
    useBundleDataImporterConfigLoadTransformationResultQuery,
    useBundleDataImporterConfigStartImportMutation,
    useBundleDataImporterConfigUploadImportFileMutation,
    useBundleDataImporterConfigUploadPreviewMutation,
    useBundleDataImporterConnectionListQuery,
    useBundleDataImporterDataTypeLoadClassAttributesQuery,
    useBundleDataImporterDataTypeLoadUnitDataQuery,
    useBundleDataImporterMappingSuggestionLoadAiQuery,
    useBundleDataImporterMappingSuggestionSuggestMutation,
    useBundleDataImporterUtilityCheckCrontabQuery,
    useBundleDataImporterAdHocImportUploadFileMutation,
    useBundleDataImporterAdHocImportInspectFileMutation,
    useBundleDataImporterAdHocImportDeleteFileMutation,
    useBundleDataImporterAdHocImportLoadMatchFieldsQuery,
    useBundleDataImporterAdHocImportLoadMappingMutation,
    useBundleDataImporterAdHocImportLoadClassificationStoreKeysQuery,
    useBundleDataImporterAdHocImportListConfigurationsQuery,
    useBundleDataImporterAdHocImportPreviewTransformationsMutation,
    useBundleDataImporterAdHocImportDryRunMutation,
    useBundleDataImporterAdHocImportStartRunMutation,
    useBundleDataImporterAdHocImportLoadRunQuery,
    useBundleDataImporterAdHocImportDiscardRunMutation,
} = injectedRtkApi;
