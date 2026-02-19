/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

export interface Permission {
  id?: number
  name: string
  read: boolean
  update: boolean
  delete: boolean
}

export interface LoaderConfig {
  type?: 'asset' | 'upload' | 'http' | 'sftp' | 'push' | 'sql'
  settings?: Record<string, any>
}

export interface InterpreterConfig {
  type?: 'csv' | 'json' | 'xml' | 'xlsx' | 'sql'
  settings?: Record<string, any>
}

export interface DataImporterFormValues {
  active: boolean
  name: string
  description: string
  group: string
  loaderConfig?: LoaderConfig
  interpreterConfig?: InterpreterConfig
  permissions: {
    roles: Permission[]
    users: Permission[]
  }
}
