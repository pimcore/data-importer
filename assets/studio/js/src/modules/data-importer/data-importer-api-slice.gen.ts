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
export type BundleDataImporterUtilityCheckCrontabApiResponse =
    /** status 200 Cron expression validation result with validity flag and error message */ BundleDataImporterCronValidationResponse;
export type BundleDataImporterUtilityCheckCrontabApiArg = {
    /** The cron expression to validate */
    cronExpression?: string;
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
        /** Cell data value */
        data?: string;
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
export type BundleDataImporterTransformationResultPreviewsResponse = {
    /** AdditionalAttributes */
    additionalAttributes?: {
        [key: string]: string | number | boolean | object;
    };
    /** Transformation result preview strings for each mapping entry */
    transformationResultPreviews: string[];
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
export const {
    useBundleDataImporterClassificationstoreLoadAttributesQuery,
    useBundleDataImporterClassificationstoreLoadKeyNameQuery,
    useBundleDataImporterClassificationstoreLoadKeysQuery,
    useBundleDataImporterConfigCalculateTransformationResultTypeQuery,
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
    useBundleDataImporterUtilityCheckCrontabQuery,
} = injectedRtkApi;
