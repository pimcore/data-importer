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

export interface LoadingStrategy {
  type?: 'notLoad' | 'id' | 'path' | 'attribute'
  settings?: Record<string, any>
}

export interface LocationStrategy {
  type?: 'staticPath' | 'findOrCreateFolder' | 'findParent' | 'noChange' | 'doNotCreate'
  settings?: Record<string, any>
}

export interface PublishingStrategy {
  type?: 'alwaysPublish' | 'attributeBased' | 'noChangePublishNew' | 'noChangeUnpublishNew'
  settings?: Record<string, any>
}

export interface ResolverConfig {
  elementType?: string
  dataObjectClassId?: string
  loadingStrategy?: LoadingStrategy
  createLocationStrategy?: LocationStrategy
  locationUpdateStrategy?: LocationStrategy
  publishingStrategy?: PublishingStrategy
}

export interface CleanupConfig {
  doCleanup?: boolean
  strategy?: 'delete' | 'unpublish'
}

export interface LoggingConfig {
  disableInfoLogs?: boolean
  disableInfoFileObjects?: boolean
  disableErrorLogs?: boolean
  disableErrorFileObjects?: boolean
}

export interface ProcessingConfig {
  executionType?: 'sequential' | 'parallel'
  doArchiveImportFile?: boolean
  disableVersioning?: boolean
  idDataIndex?: string
  doDeltaCheck?: boolean
  cleanup?: CleanupConfig
  logging?: LoggingConfig
}

export interface ExecutionConfig {
  scheduleType?: 'recurring' | 'job'
  cronDefinition?: string
  scheduledAt?: string
}

export interface TransformationPipelineItem {
  type: string
  settings?: Record<string, any>
}

export interface DataTargetConfig {
  type?: string
  settings?: {
    fieldName?: string
    language?: string
    writeIfTargetIsNotEmpty?: boolean
    writeIfSourceIsEmpty?: boolean
    [key: string]: any
  }
}

export interface ClassAttribute {
  key: string
  title: string
  localized?: boolean
}

/**
 * Normalizes a `transformationResultType` value into the key used in the
 * `attributesMap` Record.  Empty string, `'default'`, and `undefined` all
 * map to `'__default__'`; every other value is returned as-is.
 */
export function resolveAttrMapKey (transformationResultType: string | undefined): string {
  return (
    transformationResultType === undefined ||
    transformationResultType === '' ||
    transformationResultType === 'default'
  )
    ? '__default__'
    : transformationResultType
}

export interface MappingConfigItem {
  mappingId?: string
  label?: string
  dataSourceIndex?: string[]
  transformationPipeline?: TransformationPipelineItem[]
  transformationResultType?: string
  dataTarget?: DataTargetConfig
}

export function createMappingItemId (): string {
  if (typeof globalThis.crypto?.randomUUID === 'function') {
    return globalThis.crypto.randomUUID()
  }

  return `mapping-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`
}

export interface DataImporterFormValues {
  active: boolean
  name: string
  description: string
  group: string
  loaderConfig?: LoaderConfig
  interpreterConfig?: InterpreterConfig
  resolverConfig?: ResolverConfig
  mappingConfig?: MappingConfigItem[]
  processingConfig?: ProcessingConfig
  executionConfig?: ExecutionConfig
  permissions: {
    roles: Permission[]
    users: Permission[]
  }
}
