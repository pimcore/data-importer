# Studio Integration

## Studio API Endpoints

The Data Importer Bundle provides a RESTful API for the Pimcore Studio interface, enabling management of import configurations, preview data, transformations, import execution, data type lookups, and database connection listing. All endpoints are prefixed with `/pimcore-studio/api/bundle/data-importer` and require the `PLUGIN_DATA_IMPORTER_CONFIG` gate permission.

### Configuration Management

#### Get Configuration
**Endpoint:** `GET /config/{name}`
**Operation ID:** `bundle_data_importer_config_get`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Retrieves detailed information about a specific Data Importer configuration, including mappings, permissions, and column headers.

**Path Parameter:**
- `name` (string): The name of the configuration to retrieve

**Response:** `ConfigurationDetail` object

**Example:**
```
GET /config/my-product-import
```

---

#### Save Configuration
**Endpoint:** `PUT /config/{name}`
**Operation ID:** `bundle_data_importer_config_save`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level UPDATE

Saves or updates an existing Data Importer configuration. The request body must include the full configuration JSON and the current modification date for optimistic locking.

**Path Parameter:**
- `name` (string): The name of the configuration to update

**Request Body:** `ConfigurationSaveParameters` object
- `configuration` (object, required): The full configuration data
- `modificationDate` (integer, required): Current modification timestamp for conflict detection

**Response:** `ConfigurationDetail` object (the updated configuration)

**Possible Errors:**
- 404 Not Found: Configuration does not exist
- 403 Forbidden: User lacks entity-level update permission
- 409 Conflict: Modification date mismatch (concurrent edit detected)

**Example:**
```
PUT /config/my-product-import
Content-Type: application/json

{
  "configuration": { ... },
  "modificationDate": 1700000000
}
```

---

### Preview Data

#### Upload Preview Data
**Endpoint:** `POST /config/{name}/upload-preview`
**Operation ID:** `bundle_data_importer_config_upload_preview`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Uploads a file to be used as preview data for the configuration. The file is stored temporarily for use by the preview and column header endpoints.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** Multipart form data
- `file` (binary, required): The data file to upload

**Response:** HTTP 200 OK (empty response)

**Possible Errors:**
- 403 Forbidden: User lacks entity-level read permission
- 413 Max File Size Exceeded: Uploaded file exceeds size limit

**Example:**
```
POST /config/my-product-import/upload-preview
Content-Type: multipart/form-data
```

---

#### Copy Preview Data
**Endpoint:** `POST /config/{name}/copy-preview`
**Operation ID:** `bundle_data_importer_config_copy_preview`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Copies data from the configured data loader source to be used as preview data. This uses the current configuration settings to fetch data from the configured source (e.g., file system, URL).

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** `CopyPreviewParameters` object
- `currentConfig` (object, required): The current configuration state to use for loading

**Response:** HTTP 200 OK (empty response)

**Possible Errors:**
- 403 Forbidden: User lacks entity-level read permission
- 413 Max File Size Exceeded: Source data exceeds size limit

**Example:**
```
POST /config/my-product-import/copy-preview
Content-Type: application/json

{
  "currentConfig": { ... }
}
```

---

#### Load Preview Data
**Endpoint:** `POST /config/{name}/load-preview`
**Operation ID:** `bundle_data_importer_config_load_preview`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Loads preview data for a specific record index, showing how the configured interpreter parses the source data.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** `LoadPreviewParameters` object
- `currentConfig` (object, required): The current configuration state
- `recordNumber` (integer, required): The record index to preview

**Response:** `DataPreviewResponse` object containing parsed preview data rows

**Example:**
```
POST /config/my-product-import/load-preview
Content-Type: application/json

{
  "currentConfig": { ... },
  "recordNumber": 0
}
```

---

#### Load Column Headers
**Endpoint:** `POST /config/{name}/column-headers`
**Operation ID:** `bundle_data_importer_config_load_column_headers`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Loads available column headers from the preview data file. Column headers represent the data fields available for mapping.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** `CopyPreviewParameters` object
- `currentConfig` (object, required): The current configuration state

**Response:** `ColumnHeadersResponse` object containing available column headers

**Example:**
```
POST /config/my-product-import/column-headers
Content-Type: application/json

{
  "currentConfig": { ... }
}
```

---

### Transformation

#### Load Transformation Result Previews
**Endpoint:** `POST /config/{name}/transformation-result`
**Operation ID:** `bundle_data_importer_config_load_transformation_result`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Loads transformation result previews for a specific record, showing how the transformation pipeline processes source data into the target format.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** `LoadPreviewParameters` object
- `currentConfig` (object, required): The current configuration state
- `recordNumber` (integer, required): The record index to preview

**Response:** `TransformationResultPreviewsResponse` object containing transformation results per mapping

**Example:**
```
POST /config/my-product-import/transformation-result
Content-Type: application/json

{
  "currentConfig": { ... },
  "recordNumber": 0
}
```

---

#### Calculate Transformation Result Type
**Endpoint:** `POST /config/{name}/transformation-result-type`
**Operation ID:** `bundle_data_importer_config_calculate_transformation_result_type`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Calculates the result type of a transformation pipeline based on the configured operators. This is used to determine which data types are compatible with the transformation output.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** `CalculateTransformationResultTypeParameters` object
- `transformationConfig` (object, required): The transformation pipeline configuration to evaluate

**Response:** `TransformationResultTypeResponse` object with the calculated result type

**Example:**
```
POST /config/my-product-import/transformation-result-type
Content-Type: application/json

{
  "transformationConfig": { ... }
}
```

---

### Import Execution

#### Start Import
**Endpoint:** `PUT /config/{name}/start-import`
**Operation ID:** `bundle_data_importer_config_start_import`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level UPDATE

Starts the import execution for a configuration. The import processes all records from the configured data source.

**Path Parameter:**
- `name` (string): The name of the configuration

**Response:** `ImportStartResponse` object confirming the import has started

**Example:**
```
PUT /config/my-product-import/start-import
```

---

#### Check Import Progress
**Endpoint:** `GET /config/{name}/check-import-progress`
**Operation ID:** `bundle_data_importer_config_check_import_progress`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Checks the current progress of a running import, including completion percentage and status.

**Path Parameter:**
- `name` (string): The name of the configuration

**Response:** `ImportProgressResponse` object with progress details

**Example:**
```
GET /config/my-product-import/check-import-progress
```

---

#### Cancel Import Execution
**Endpoint:** `PUT /config/{name}/cancel-execution`
**Operation ID:** `bundle_data_importer_config_cancel_execution`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level UPDATE

Cancels a currently running import execution for the specified configuration.

**Path Parameter:**
- `name` (string): The name of the configuration

**Response:** HTTP 200 OK (empty response)

**Example:**
```
PUT /config/my-product-import/cancel-execution
```

---

#### Upload Import File
**Endpoint:** `POST /config/{name}/upload-import-file`
**Operation ID:** `bundle_data_importer_config_upload_import_file`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level UPDATE

Uploads a data file to be used as the import source. This file replaces the configured loader source for the next import execution.

**Path Parameter:**
- `name` (string): The name of the configuration

**Request Body:** Multipart form data
- `file` (binary, required): The data file to upload for import

**Response:** HTTP 200 OK (empty response)

**Example:**
```
POST /config/my-product-import/upload-import-file
Content-Type: multipart/form-data
```

---

#### Check Import File Status
**Endpoint:** `GET /config/{name}/has-import-file-uploaded`
**Operation ID:** `bundle_data_importer_config_has_import_file_uploaded`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG` + entity-level READ

Checks whether an import file has been uploaded for the specified configuration.

**Path Parameter:**
- `name` (string): The name of the configuration

**Response:** `ImportFileStatusResponse` object with `hasFile` boolean

**Example:**
```
GET /config/my-product-import/has-import-file-uploaded
```

---

### Utility

#### Check Crontab Expression
**Endpoint:** `GET /utility/check-crontab`
**Operation ID:** `bundle_data_importer_utility_check_crontab`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Validates a cron expression and returns whether it is syntactically correct. Used by the configuration UI to verify scheduling expressions.

**Query Parameter:**
- `cronExpression` (string, optional): The cron expression to validate (e.g., `*/5 * * * *`)

**Response:** `CronValidationResponse` object with validation result

**Example:**
```
GET /utility/check-crontab?cronExpression=*/5 * * * *
```

---

### Data Types

#### Load Class Attributes
**Endpoint:** `GET /data-type/class-attributes`
**Operation ID:** `bundle_data_importer_data_type_load_class_attributes`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Loads the available attributes for a data object class, used to populate mapping target fields. Supports filtering by transformation result type and toggling advanced relation or system attributes.

**Query Parameters:**
- `classId` (string, required): The class ID (e.g., `Product`)
- `transformationResultType` (string, optional): Filter attributes by compatible result type
- `loadAdvancedRelations` (boolean, optional): Include advanced relation attributes (default: `false`)
- `systemRead` (boolean, optional): Include system read attributes (default: `false`)
- `systemWrite` (boolean, optional): Include system write attributes (default: `false`)

**Response:** `ClassAttributesResponse` object containing attribute tree

**Example:**
```
GET /data-type/class-attributes?classId=Product
GET /data-type/class-attributes?classId=Product&transformationResultType=default&systemWrite=true
```

---

#### Load Unit Data
**Endpoint:** `GET /data-type/unit-data`
**Operation ID:** `bundle_data_importer_data_type_load_unit_data`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Loads all available quantity value units. Used to populate unit selection dropdowns in the mapping configuration.

**Response:** `UnitDataResponse` object containing available units

**Example:**
```
GET /data-type/unit-data
```

---

### Classification Store

#### Load Classification Store Attributes
**Endpoint:** `GET /classificationstore/attributes`
**Operation ID:** `bundle_data_importer_classificationstore_load_attributes`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Loads classification store field attributes for a given data object class. Returns the available classification store fields that can be used as mapping targets.

**Query Parameter:**
- `classId` (string, required): The class ID (e.g., `Product`)

**Response:** `ClassAttributesResponse` object containing classification store field attributes

**Example:**
```
GET /classificationstore/attributes?classId=Product
```

---

#### Load Classification Store Keys
**Endpoint:** `GET /classificationstore/keys`
**Operation ID:** `bundle_data_importer_classificationstore_load_keys`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Loads classification store keys for a specific field, with support for pagination, sorting, and filtering. Returns key entries with their group/key names and IDs.

**Query Parameters:**
- `classId` (string, required): The class ID (e.g., `Product`)
- `fieldName` (string, required): The classification store field name (e.g., `classificationStore`)
- `transformationResultType` (string, required): The transformation result type to filter by
- `start` (integer, optional): Pagination offset (default: `0`)
- `limit` (integer, optional): Maximum number of results (default: `15`)
- `sort` (string, optional): JSON-encoded sort configuration
- `searchfilter` (string, optional): Text search filter
- `filter` (string, optional): JSON-encoded column filter

**Response:** `ClassificationStoreKeysResponse` object containing paginated key entries

**Example:**
```
GET /classificationstore/keys?classId=Product&fieldName=classificationStore&transformationResultType=default
GET /classificationstore/keys?classId=Product&fieldName=classificationStore&transformationResultType=default&start=0&limit=15&searchfilter=color
```

---

#### Load Classification Store Key Name
**Endpoint:** `GET /classificationstore/key-name`
**Operation ID:** `bundle_data_importer_classificationstore_load_key_name`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Resolves a classification store key ID to its human-readable group and key names. The key ID format is `{groupId}-{keyId}`.

**Query Parameter:**
- `keyId` (string, required): The key identifier in `{groupId}-{keyId}` format (e.g., `1-5`)

**Response:** `ClassificationStoreKeyNameResponse` object with resolved group and key names

**Example:**
```
GET /classificationstore/key-name?keyId=1-5
```

---

### Connections

#### List Database Connections
**Endpoint:** `GET /connection/list`
**Operation ID:** `bundle_data_importer_connection_list`
**Permission:** `PLUGIN_DATA_IMPORTER_CONFIG`

Lists all available Doctrine database connections. Used by the configuration UI to populate connection selection dropdowns.

**Response:** `ConnectionsResponse` object containing an array of connection entries, each with a human-readable `name` and a Doctrine service identifier `value`.

**Example:**
```
GET /connection/list
```

**Example Response:**
```json
{
  "connections": [
    {"name": "default", "value": "doctrine.dbal.default_connection"}
  ],
  "additionalAttributes": []
}
```

---

## Permissions

The Studio API uses a two-tier permission model:

- **Gate-level permission (`PLUGIN_DATA_IMPORTER_CONFIG`)**: Required for all endpoints. Enforced via `#[IsGranted]` attribute on controllers. Users without this permission receive a 401 Unauthorized response.
- **Entity-level permissions (READ/UPDATE)**: Checked in service layer for configuration-specific endpoints. Users with the gate permission but lacking entity-level access to a specific configuration receive a 403 Forbidden response.

| Endpoint Group | Gate Permission | Entity Permission |
|---|---|---|
| Get Configuration, Preview Data, Column Headers, Transformation Result, Check Progress, Check Import File | `PLUGIN_DATA_IMPORTER_CONFIG` | READ |
| Save Configuration, Start Import, Cancel Execution, Upload Import File | `PLUGIN_DATA_IMPORTER_CONFIG` | UPDATE |
| Utility, Data Types, Classification Store, Connections | `PLUGIN_DATA_IMPORTER_CONFIG` | None |

## Common Response Codes

- **200 OK**: Successful request
- **400 Bad Request**: Invalid request data or parameters
- **401 Unauthorized**: Missing or invalid authentication, or user lacks gate permission
- **403 Forbidden**: User lacks entity-level permission for the specific configuration
- **404 Not Found**: Configuration not found
- **409 Conflict**: Modification date mismatch during save (optimistic locking)
- **413 Max File Size Exceeded**: Uploaded file exceeds server limits

## API Tags

All endpoints are tagged with `Bundle Data Importer` for OpenAPI documentation grouping.

## Usage Examples

### Managing Configurations

1. **Get configuration details:**
   ```
   GET /config/my-product-import
   ```

2. **Save configuration changes:**
   ```
   PUT /config/my-product-import
   Content-Type: application/json

   {
     "configuration": { ... },
     "modificationDate": 1700000000
   }
   ```

### Working with Preview Data

1. **Upload a preview file:**
   ```
   POST /config/my-product-import/upload-preview
   Content-Type: multipart/form-data
   ```

2. **Or copy from configured source:**
   ```
   POST /config/my-product-import/copy-preview
   Content-Type: application/json

   {"currentConfig": { ... }}
   ```

3. **Load column headers:**
   ```
   POST /config/my-product-import/column-headers
   Content-Type: application/json

   {"currentConfig": { ... }}
   ```

4. **Preview a specific record:**
   ```
   POST /config/my-product-import/load-preview
   Content-Type: application/json

   {"currentConfig": { ... }, "recordNumber": 0}
   ```

### Running an Import

1. **Upload an import file (optional):**
   ```
   POST /config/my-product-import/upload-import-file
   Content-Type: multipart/form-data
   ```

2. **Check if import file exists:**
   ```
   GET /config/my-product-import/has-import-file-uploaded
   ```

3. **Start the import:**
   ```
   PUT /config/my-product-import/start-import
   ```

4. **Monitor progress:**
   ```
   GET /config/my-product-import/check-import-progress
   ```

5. **Cancel if needed:**
   ```
   PUT /config/my-product-import/cancel-execution
   ```

### Looking Up Data Types

1. **Load class attributes for mapping:**
   ```
   GET /data-type/class-attributes?classId=Product
   ```

2. **Load quantity value units:**
   ```
   GET /data-type/unit-data
   ```

3. **Load classification store attributes:**
   ```
   GET /classificationstore/attributes?classId=Product
   ```

4. **Browse classification store keys:**
   ```
   GET /classificationstore/keys?classId=Product&fieldName=classificationStore&transformationResultType=default
   ```

5. **Resolve a key name:**
   ```
   GET /classificationstore/key-name?keyId=1-5
   ```

### Validating Cron Expressions

```
GET /utility/check-crontab?cronExpression=*/5 * * * *
```

### Listing Database Connections

```
GET /connection/list
```
