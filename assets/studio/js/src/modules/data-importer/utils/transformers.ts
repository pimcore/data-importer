/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */

import { type DataImporterFormValues, type LoaderConfig, type InterpreterConfig, type ResolverConfig, type ProcessingConfig, type ExecutionConfig, type Permission, type MappingConfigItem } from '../types'
import { ensureMappingId } from '../components/tabs/steps/mapping-step/utils/mapping-identity'

export interface BackendPermission {
  id?: number
  name?: string
  read?: boolean
  update?: boolean
  delete?: boolean
}

export interface BackendConfiguration {
  general?: {
    active: boolean
    description: string
    group: string
    [key: string]: any
  }
  loaderConfig?: LoaderConfig
  interpreterConfig?: InterpreterConfig
  resolverConfig?: ResolverConfig
  mappingConfig?: MappingConfigItem[]
  processingConfig?: ProcessingConfig
  executionConfig?: ExecutionConfig
  permissions?: {
    roles?: BackendPermission[]
    users?: BackendPermission[]
  }
  [key: string]: any
}

export function transformPermissionToBackend (permission: Permission): BackendPermission {
  return {
    id: permission.id,
    name: permission.name,
    read: permission.read ?? false,
    update: permission.update ?? false,
    delete: permission.delete ?? false
  }
}

export function transformPermissionFromBackend (backendPermission: BackendPermission): Permission {
  return {
    id: backendPermission.id,
    name: backendPermission.name ?? '',
    read: backendPermission.read ?? false,
    update: backendPermission.update ?? false,
    delete: backendPermission.delete ?? false
  }
}

export function transformPermissionsToBackend (permissions: DataImporterFormValues['permissions'] | undefined): BackendConfiguration['permissions'] {
  return {
    roles: (permissions?.roles ?? []).map(transformPermissionToBackend),
    users: (permissions?.users ?? []).map(transformPermissionToBackend)
  }
}

export function transformPermissionsFromBackend (backendPermissions: BackendConfiguration['permissions']): DataImporterFormValues['permissions'] {
  return {
    roles: (backendPermissions?.roles ?? []).map(transformPermissionFromBackend),
    users: (backendPermissions?.users ?? []).map(transformPermissionFromBackend)
  }
}

export function transformFormToBackend (
  formValues: DataImporterFormValues,
  existingConfig: BackendConfiguration
): BackendConfiguration {
  const mergedLoaderConfig = mergeOptionConfig(formValues.loaderConfig, existingConfig.loaderConfig)
  const mergedInterpreterConfig = mergeOptionConfig(formValues.interpreterConfig, existingConfig.interpreterConfig)

  const normalizedLoaderConfig = normalizeLoaderConfig(mergedLoaderConfig)
  const normalizedInterpreterConfig = normalizeInterpreterConfig(mergedInterpreterConfig)

  return {
    ...existingConfig,
    general: {
      ...existingConfig.general,
      active: formValues.active,
      description: formValues.description,
      group: formValues.group
    },
    loaderConfig: normalizedLoaderConfig,
    interpreterConfig: normalizedInterpreterConfig,
    resolverConfig: formValues.resolverConfig ?? existingConfig.resolverConfig,
    mappingConfig: formValues.mappingConfig ?? existingConfig.mappingConfig,
    processingConfig: formValues.processingConfig ?? existingConfig.processingConfig,
    executionConfig: formValues.executionConfig ?? existingConfig.executionConfig,
    permissions: transformPermissionsToBackend(formValues.permissions)
  }
}

function mergeOptionConfig<T extends { type?: string, settings?: Record<string, unknown> }>
(
  formConfig: T | undefined,
  existingConfig: T | undefined
): T | undefined {
  if (formConfig === undefined) {
    return existingConfig
  }

  // When step fields are currently unmounted, form snapshots can contain
  // empty objects like {} for loader/interpreter config. In that case we must
  // keep the persisted config instead of overwriting it with an empty object.
  if (formConfig.type === undefined || formConfig.type === '') {
    return existingConfig ?? formConfig
  }

  return {
    ...existingConfig,
    ...formConfig,
    settings: {
      ...(existingConfig?.settings ?? {}),
      ...(formConfig.settings ?? {})
    }
  }
}

function normalizeToString (value: unknown): string {
  if (value === undefined || value === null) {
    return ''
  }

  return String(value)
}

function normalizeToBoolean (value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value
  }

  if (value === '1' || value === 1 || value === 'true') {
    return true
  }

  return false
}

function getLoaderSettings (loaderConfig: LoaderConfig | undefined): Record<string, unknown> {
  return (loaderConfig?.settings as Record<string, unknown> | undefined) ?? {}
}

function normalizeLoaderConfig (loaderConfig: LoaderConfig | undefined): LoaderConfig | undefined {
  if (loaderConfig?.type === undefined) {
    return loaderConfig
  }

  const settings = getLoaderSettings(loaderConfig)

  if (loaderConfig.type === 'asset') {
    return {
      ...loaderConfig,
      settings: {
        assetPath: normalizeToString(settings.assetPath)
      }
    }
  }

  if (loaderConfig.type === 'upload') {
    return {
      ...loaderConfig,
      settings: {
        uploadFilePath: normalizeToString(settings.uploadFilePath)
      }
    }
  }

  if (loaderConfig.type === 'http') {
    const normalizedSchema = normalizeToString(settings.schema)
    const normalizedUrl = stripSchemaPrefix(normalizeToString(settings.url))

    return {
      ...loaderConfig,
      settings: {
        schema: normalizedSchema,
        url: normalizedUrl
      }
    }
  }

  if (loaderConfig.type === 'sftp') {
    return {
      ...loaderConfig,
      settings: {
        host: normalizeToString(settings.host),
        port: normalizeToString(settings.port),
        username: normalizeToString(settings.username),
        password: normalizeToString(settings.password),
        remotePath: normalizeToString(settings.remotePath)
      }
    }
  }

  if (loaderConfig.type === 'push') {
    return {
      ...loaderConfig,
      settings: {
        apiKey: normalizeToString(settings.apiKey),
        ignoreNotEmptyQueue: normalizeToBoolean(settings.ignoreNotEmptyQueue)
      }
    }
  }

  if (loaderConfig.type === 'sql') {
    return {
      ...loaderConfig,
      settings: {
        connection: normalizeToString(settings.connection),
        select: normalizeToString(settings.select),
        from: normalizeToString(settings.from),
        where: normalizeToString(settings.where),
        groupBy: normalizeToString(settings.groupBy)
      }
    }
  }

  return loaderConfig
}

function normalizeInterpreterConfig (interpreterConfig: InterpreterConfig | undefined): InterpreterConfig | undefined {
  if (interpreterConfig?.type === undefined) {
    return interpreterConfig
  }

  const settings = (interpreterConfig.settings as Record<string, unknown> | undefined) ?? {}

  if (interpreterConfig.type === 'csv') {
    return {
      ...interpreterConfig,
      settings: {
        skipFirstRow: normalizeToBoolean(settings.skipFirstRow),
        saveHeaderName: normalizeToBoolean(settings.saveHeaderName),
        delimiter: normalizeToString(settings.delimiter),
        enclosure: normalizeToString(settings.enclosure),
        escape: normalizeToString(settings.escape)
      }
    }
  }

  if (interpreterConfig.type === 'json') {
    return {
      ...interpreterConfig,
      settings: {
        path: normalizeToString(settings.path)
      }
    }
  }

  if (interpreterConfig.type === 'xml') {
    return {
      ...interpreterConfig,
      settings: {
        xpath: normalizeToString(settings.xpath),
        schema: normalizeToString(settings.schema)
      }
    }
  }

  if (interpreterConfig.type === 'xlsx') {
    return {
      ...interpreterConfig,
      settings: {
        skipFirstRow: normalizeToBoolean(settings.skipFirstRow),
        sheetName: normalizeToString(settings.sheetName)
      }
    }
  }

  if (interpreterConfig.type === 'sql') {
    return {
      ...interpreterConfig,
      settings: {}
    }
  }

  return interpreterConfig
}

function stripSchemaPrefix (url: string): string {
  return url.replace(/^\s*[a-z][a-z0-9+.-]*:\/\//i, '')
}

export function transformBackendToForm (
  backendConfig: BackendConfiguration,
  configName: string
): DataImporterFormValues {
  const mappingConfig = (backendConfig.mappingConfig ?? []).map((item) => ({
    ...ensureMappingId(item)
  }))

  return {
    active: backendConfig.general?.active ?? false,
    name: configName,
    description: backendConfig.general?.description ?? '',
    group: backendConfig.general?.group ?? '',
    loaderConfig: backendConfig.loaderConfig,
    interpreterConfig: backendConfig.interpreterConfig,
    resolverConfig: backendConfig.resolverConfig,
    mappingConfig,
    processingConfig: backendConfig.processingConfig,
    executionConfig: backendConfig.executionConfig,
    permissions: transformPermissionsFromBackend(backendConfig.permissions)
  }
}
