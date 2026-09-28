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

export const DEFAULT_ATTR_MAP_KEY = '__default__'

// Data targets that write into advanced relation fields. For these the backend only offers
// advancedManyToMany(Object)Relation fields when the attributes are requested with
// loadAdvancedRelations, so their attributes are kept under a separate map key.
const ADVANCED_RELATION_DATA_TARGET_TYPES = ['manyToManyRelation']
const ADVANCED_RELATION_RESULT_TYPES = ['dataObjectArray', 'assetArray']
const ADVANCED_RELATIONS_ATTR_MAP_KEY_PREFIX = 'advancedRelations:'

export function resolveAttrMapKey (transformationResultType: string | undefined, dataTargetType?: string): string {
  if (
    transformationResultType === undefined ||
    transformationResultType === '' ||
    transformationResultType === 'default'
  ) {
    return DEFAULT_ATTR_MAP_KEY
  }

  if (
    ADVANCED_RELATION_DATA_TARGET_TYPES.includes(dataTargetType ?? '') &&
    ADVANCED_RELATION_RESULT_TYPES.includes(transformationResultType)
  ) {
    return ADVANCED_RELATIONS_ATTR_MAP_KEY_PREFIX + transformationResultType
  }

  return transformationResultType
}

export function isAdvancedRelationsAttrMapKey (mapKey: string): boolean {
  return mapKey.startsWith(ADVANCED_RELATIONS_ATTR_MAP_KEY_PREFIX)
}

/**
 * Turns an attributes map key back into the parameters of the class-attributes request.
 */
export function parseAttrMapKey (mapKey: string): { transformationResultType?: string, loadAdvancedRelations?: boolean } {
  if (mapKey === DEFAULT_ATTR_MAP_KEY) {
    return {}
  }

  if (isAdvancedRelationsAttrMapKey(mapKey)) {
    return {
      transformationResultType: mapKey.slice(ADVANCED_RELATIONS_ATTR_MAP_KEY_PREFIX.length),
      loadAdvancedRelations: true
    }
  }

  return { transformationResultType: mapKey }
}

export interface MappingConfigItem {
  mappingId?: string
  label?: string
  dataSourceIndex?: string[]
  transformationPipeline?: TransformationPipelineItem[]
  transformationResultType?: string
  dataTarget?: DataTargetConfig
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
