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
import type { ExecutionConfig, ProcessingConfig, ResolverConfig } from '../types'
import { humanize, translated } from './field-labels'
import { SECTION_LABELS } from './config-review-model'

type Translate = (key: string, options?: Record<string, unknown>) => string

const T = 'data-importer.review.outline'

const SEP = ' · '

/** what a section is worth saying in one line, and how much of it that line accounts for */
interface Told {
  readonly value: string
  readonly note?: string
  /** document paths the line already names, so the tail does not count them twice */
  readonly covers: string[]
}

export interface BriefSection {
  /** the editor section this is about; the same key the change rail navigates by */
  readonly key: string
  /** translation key */
  readonly label: string
  /** the setting that says most about the section, when it has one */
  readonly value?: string
  /** its particulars, and how many settings the line did not get to */
  readonly note?: string
}

/** A configuration that does not exist yet, told section by section rather than field by field. */
export interface ConfigBrief {
  readonly name: string
  readonly active: boolean
  readonly description?: string
  readonly sections: BriefSection[]
  readonly total: number
  readonly filled: number
}

/** an adapter's or strategy's name as the editor shows it in its select, else its key spelt out */
const named = (t: Translate, prefix: string, type: string | undefined): string | undefined =>
  type === undefined || type === '' ? undefined : translated(t, `${prefix}.${type}`) ?? humanize(type)

const present = (parts: Array<string | undefined>): string[] =>
  parts.filter((part): part is string => part !== undefined && part !== '')

const text = (value: unknown): string | undefined =>
  typeof value === 'string' && value !== '' ? value : undefined

/** the loader setting that says where the data comes from, and the path it lives at */
const ORIGIN_KEY: Record<string, string[]> = {
  asset: ['assetPath'],
  http: ['url'],
  sftp: ['host', 'remotePath'],
  sql: ['from'],
  push: ['endpoint']
}

function origin (
  type: string | undefined,
  settings: Record<string, unknown> | undefined
): { note?: string, covers: string[] } {
  const keys = ORIGIN_KEY[type ?? ''] ?? []
  if (settings === undefined || keys.length === 0) return { covers: [] }

  const parts = present(keys.map((key) => text(settings[key])))

  return {
    note: parts.length === 0 ? undefined : parts.join(type === 'sftp' ? ':' : SEP),
    covers: keys.map((key) => `loaderConfig.settings.${key}`)
  }
}

/** where the data comes from and how it is read */
function dataSourceTold (configuration: BackendConfiguration, t: Translate): Told | undefined {
  const loader = configuration.loaderConfig
  const interpreter = configuration.interpreterConfig
  const value = present([
    named(t, 'data-importer.loader', loader?.type),
    named(t, 'data-importer.interpreter', interpreter?.type)
  ]).join(SEP)
  if (value === '') return undefined

  const from = origin(loader?.type, loader?.settings)

  return {
    value,
    note: from.note,
    covers: ['loaderConfig.type', 'interpreterConfig.type', ...from.covers]
  }
}

function mappingTold (configuration: BackendConfiguration, t: Translate): Told | undefined {
  const rows = configuration.mappingConfig?.length ?? 0
  if (rows === 0) return undefined

  // the list is the whole of this section, so the line leaves nothing to count
  return { value: t(`${T}.mappings`, { count: rows }), covers: ['mappingConfig'] }
}

/** what the import writes, where new elements land and whether they go live */
function resolverTold (config: ResolverConfig | undefined, t: Translate): Told | undefined {
  if (config === undefined) return undefined
  const target = text(config.dataObjectClassId)
  const value = config.elementType === 'dataObject' && target !== undefined
    ? t(`${T}.data-objects`, { class: target })
    : target ?? named(t, 'data-importer.resolver.element', config.elementType)
  if (value === undefined || value === '') return undefined

  const location = named(t, 'data-importer.resolver.location-strategy', config.createLocationStrategy?.type)
  const note = present([
    location === undefined
      ? undefined
      : present([location, text(config.createLocationStrategy?.settings?.path)]).join(' '),
    named(t, 'data-importer.resolver.publishing-strategy', config.publishingStrategy?.type)
  ]).join(SEP)

  return {
    value,
    note: note === '' ? undefined : note,
    covers: [
      'resolverConfig.dataObjectClassId',
      'resolverConfig.elementType',
      'resolverConfig.createLocationStrategy.type',
      'resolverConfig.createLocationStrategy.settings.path',
      'resolverConfig.publishingStrategy.type'
    ]
  }
}

/** how it runs, and the switches that decide what happens to what the feed stops carrying */
function processingTold (processing: ProcessingConfig | undefined, t: Translate): Told | undefined {
  const value = named(t, 'data-importer.processing.execution-type', processing?.executionType)
  if (value === undefined || value === '') return undefined

  const cleanup = processing?.cleanup?.doCleanup === true
    ? present([
        translated(t, 'data-importer.processing.cleanup.title') ?? humanize('cleanup'),
        named(t, 'data-importer.processing.cleanup.strategy', processing.cleanup.strategy)
      ]).join(': ')
    : undefined
  const note = present([
    processing?.doDeltaCheck === true
      ? translated(t, 'data-importer.processing.delta-check') ?? humanize('deltaCheck')
      : undefined,
    cleanup
  ]).join(SEP)

  return {
    value,
    note: note === '' ? undefined : note,
    covers: [
      'processingConfig.executionType',
      'processingConfig.doDeltaCheck',
      'processingConfig.cleanup.doCleanup',
      'processingConfig.cleanup.strategy'
    ]
  }
}

/** when it runs: a cron line, a one-off date, or nothing and somebody presses the button */
function executionTold (execution: ExecutionConfig | undefined, t: Translate): Told | undefined {
  const schedule = text(execution?.cronDefinition) ??
    (execution?.scheduleType === 'job' ? text(execution.scheduledAt) : undefined)

  return {
    value: schedule ?? translated(t, 'data-importer.execution.manual-execution') ?? humanize('manualExecution'),
    covers: ['executionConfig.scheduleType', 'executionConfig.cronDefinition', 'executionConfig.scheduledAt']
  }
}

/** the subject's own identity and the Data Hub's bookkeeping, never a setting somebody chose */
const NOT_A_SETTING = new Set(['name', 'type', 'path', 'modificationDate', 'createDate', 'writeable'])

/**
 * Leaves under a section that actually hold a value — an unset field is not a setting. The
 * skip list applies to the section's own keys only: `general.type` is the subject's kind,
 * `loaderConfig.type` is a choice somebody made.
 */
function settingsIn (node: unknown, skip?: ReadonlySet<string>): number {
  if (Array.isArray(node)) return node.length
  if (typeof node !== 'object' || node === null) {
    return node === undefined || node === '' || node === false ? 0 : 1
  }

  return Object.entries(node as Record<string, unknown>)
    .filter(([key]) => skip?.has(key) !== true)
    .reduce((sum, [, value]) => sum + settingsIn(value), 0)
}

/** whether the document holds a value at a path, so a covered leaf is only discounted once */
function filledAt (configuration: BackendConfiguration, path: string): number {
  let node: unknown = configuration
  for (const segment of path.split('.')) {
    if (typeof node !== 'object' || node === null) return 0
    node = (node as Record<string, unknown>)[segment]
  }

  return settingsIn(node)
}

/** the editor's sections, in the order it lays them out */
const SECTIONS: Array<{
  key: string
  keys: Array<keyof BackendConfiguration>
  skip?: ReadonlySet<string>
  tell?: (configuration: BackendConfiguration, t: Translate) => Told | undefined
}> = [
  { key: 'general', keys: ['general'], skip: NOT_A_SETTING },
  { key: 'dataSource', keys: ['loaderConfig', 'interpreterConfig'], tell: dataSourceTold },
  { key: 'resolver', keys: ['resolverConfig'], tell: (c, t) => resolverTold(c.resolverConfig, t) },
  { key: 'mapping', keys: ['mappingConfig'], tell: mappingTold },
  { key: 'processing', keys: ['processingConfig'], tell: (c, t) => processingTold(c.processingConfig, t) },
  { key: 'execution', keys: ['executionConfig'], tell: (c, t) => executionTold(c.executionConfig, t) },
  { key: 'permissions', keys: ['permissions'] }
]

/**
 * A configuration that does not exist yet, told once. Every leaf of it is "added", so marking
 * them one by one says only "all of it" at great length: each section says instead what it is
 * set to, and how much of it the line did not get to.
 */
export function configBrief (configuration: BackendConfiguration, t: Translate): ConfigBrief {
  const sections: BriefSection[] = []
  let filled = 0

  for (const { key, keys, skip, tell } of SECTIONS) {
    const count = keys.reduce<number>((sum, k) => sum + settingsIn(configuration[k], skip), 0)
    if (count === 0) continue
    filled += count

    const told = tell?.(configuration, t)
    const covered = told?.covers.reduce((sum, path) => sum + filledAt(configuration, path), 0) ?? 0
    const rest = Math.max(0, count - covered)

    if (told === undefined) {
      // nothing worth a line of its own: the count is the line, not a note under one
      sections.push({ key, label: SECTION_LABELS[key] ?? key, value: t(`${T}.settings-count`, { count }) })

      continue
    }

    const note = present([told.note, rest === 0 ? undefined : t(`${T}.more-settings`, { count: rest })]).join(SEP)

    sections.push({
      key,
      label: SECTION_LABELS[key] ?? key,
      value: told.value,
      ...note === '' ? {} : { note }
    })
  }

  return {
    name: text(configuration.general?.name) ?? '',
    active: configuration.general?.active === true,
    description: text(configuration.general?.description),
    sections,
    total: sections.length,
    filled
  }
}
