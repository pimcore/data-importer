/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type DataImporterFormValues, type LoaderConfig, type InterpreterConfig, type Permission } from '../types'

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
  return {
    ...existingConfig,
    general: {
      ...existingConfig.general,
      active: formValues.active,
      description: formValues.description,
      group: formValues.group
    },
    loaderConfig: formValues.loaderConfig ?? existingConfig.loaderConfig,
    interpreterConfig: formValues.interpreterConfig ?? existingConfig.interpreterConfig,
    permissions: transformPermissionsToBackend(formValues.permissions)
  }
}

export function transformBackendToForm (
  backendConfig: BackendConfiguration,
  configName: string
): DataImporterFormValues {
  return {
    active: backendConfig.general?.active ?? false,
    name: configName,
    description: backendConfig.general?.description ?? '',
    group: backendConfig.general?.group ?? '',
    loaderConfig: backendConfig.loaderConfig,
    interpreterConfig: backendConfig.interpreterConfig,
    permissions: transformPermissionsFromBackend(backendConfig.permissions)
  }
}
