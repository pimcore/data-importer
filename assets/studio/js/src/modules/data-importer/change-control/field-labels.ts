/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import type { BackendConfiguration } from '../utils/transformers'

type Translate = (key: string) => string

/** the fields the editor labels itself, by document path */
const LABEL_KEYS: Record<string, string> = {
  'loaderConfig.type': 'data-importer.data-source.type-label',
  'interpreterConfig.type': 'data-importer.file-format.title',
  'resolverConfig.dataObjectClassId': 'data-importer.resolver.class',
  'resolverConfig.loadingStrategy.type': 'data-importer.resolver.loading-strategy',
  'resolverConfig.createLocationStrategy.type': 'data-importer.resolver.create-location-strategy',
  'resolverConfig.locationUpdateStrategy.type': 'data-importer.resolver.location-update-strategy',
  'resolverConfig.publishingStrategy.type': 'data-importer.resolver.publishing-strategy',
  'processingConfig.executionType': 'data-importer.processing.execution-type',
  'processingConfig.idDataIndex': 'data-importer.processing.id-data-index',
  'processingConfig.doDeltaCheck': 'data-importer.processing.delta-check',
  'processingConfig.doDeltaCheckCheck': 'data-importer.processing.delta-check',
  'processingConfig.doArchiveImportFile': 'data-importer.processing.archive-import-file',
  'processingConfig.disableVersioning': 'data-importer.processing.disable-versioning',
  'processingConfig.cleanup.doCleanup': 'data-importer.processing.cleanup.do-cleanup',
  'processingConfig.cleanup.strategy': 'data-importer.processing.cleanup.strategy',
  'processingConfig.logging.disableInfoLogs': 'data-importer.processing.logging.info.disable-logs',
  'processingConfig.logging.disableInfoFileObjects': 'data-importer.processing.logging.info.disable-file-objects',
  'processingConfig.logging.disableErrorLogs': 'data-importer.processing.logging.error.disable-logs',
  'processingConfig.logging.disableErrorFileObjects': 'data-importer.processing.logging.error.disable-file-objects',
  'executionConfig.scheduleType': 'data-importer.execution.schedule-type',
  'executionConfig.cronDefinition': 'data-importer.execution.cron-definition',
  'executionConfig.scheduledAt': 'data-importer.execution.scheduled-at'
}

/** the strategy a setting belongs to, so "path" reads as "Location Strategy · Path" */
const STRATEGY_KEYS: Record<string, string> = {
  loadingStrategy: 'data-importer.resolver.loading-strategy',
  createLocationStrategy: 'data-importer.resolver.create-location-strategy',
  locationUpdateStrategy: 'data-importer.resolver.location-update-strategy',
  publishingStrategy: 'data-importer.resolver.publishing-strategy'
}

const kebab = (key: string): string => key.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase()

/** camelCase to words: what a person would call a key the editor has no label for */
export const humanize = (key: string): string => {
  const words = key.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/[_-]+/g, ' ').trim()
  return words.charAt(0).toUpperCase() + words.slice(1)
}

/** t() hands the key back when it has no translation; that is the signal to fall back */
const translated = (t: Translate, key: string): string | undefined => {
  const label = t(key)
  return label === key ? undefined : label
}

/**
 * The label the editor shows for a document path — the same words a reader sees next to
 * the field, not the key the document stores it under. Settings under a loader or
 * interpreter take the label of the adapter that is selected; anything unlabelled reads
 * as its key, spelt out.
 */
export function fieldLabel (address: string, configuration: BackendConfiguration | undefined, t: Translate): string {
  const direct = LABEL_KEYS[address]
  if (direct !== undefined) return translated(t, direct) ?? humanize(address.split('.').pop() ?? address)

  const segments = address.split('.')
  const leaf = segments[segments.length - 1]

  if (segments[0] === 'loaderConfig' && segments[1] === 'settings') {
    const type = (configuration?.loaderConfig as { type?: string } | undefined)?.type ?? ''
    return translated(t, `data-importer.loader.${type}.${kebab(leaf)}`) ?? humanize(leaf)
  }
  if (segments[0] === 'interpreterConfig' && segments[1] === 'settings') {
    const type = (configuration?.interpreterConfig as { type?: string } | undefined)?.type ?? ''
    return translated(t, `data-importer.interpreter.${type}.${kebab(leaf)}`) ?? humanize(leaf)
  }
  if (segments[0] === 'resolverConfig' && segments[2] === 'settings' && STRATEGY_KEYS[segments[1]] !== undefined) {
    const strategy = translated(t, STRATEGY_KEYS[segments[1]]) ?? humanize(segments[1])
    return `${strategy} · ${humanize(leaf)}`
  }

  return humanize(leaf)
}
