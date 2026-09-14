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

export interface BriefStop {
  readonly key: string
  /** a Studio icon name */
  readonly icon: string
  /** translation key for the part this stop plays in the pipeline */
  readonly role: string
  readonly value: string
  /** the particulars, when the document carries any */
  readonly note?: string
}

export interface BriefGroup {
  readonly section: string
  /** translation key */
  readonly label: string
  readonly count: number
}

/** A configuration that does not exist yet, in one card: what it is, what it does, how much of it there is. */
export interface ConfigBrief {
  readonly name: string
  readonly active: boolean
  readonly description?: string
  readonly stops: BriefStop[]
  readonly groups: BriefGroup[]
  readonly total: number
}

/** the file format decides the Read mark; the loader only says where the file comes from */
const INTERPRETER_ICON: Record<string, string> = {
  csv: 'import-csv',
  json: 'json',
  xml: 'code',
  xlsx: 'table',
  sql: 'table'
}

/** an adapter's or strategy's name as the editor shows it in its select, else its key spelt out */
const named = (t: Translate, prefix: string, type: string | undefined): string | undefined =>
  type === undefined || type === '' ? undefined : translated(t, `${prefix}.${type}`) ?? humanize(type)

const present = (parts: Array<string | undefined>): string[] =>
  parts.filter((part): part is string => part !== undefined && part !== '')

const text = (value: unknown): string | undefined =>
  typeof value === 'string' && value !== '' ? value : undefined

/** the one loader setting that says where the data comes from */
function origin (type: string | undefined, settings: Record<string, unknown> | undefined): string | undefined {
  if (settings === undefined) return undefined
  const s = (key: string): string | undefined => text(settings[key])
  switch (type) {
    case 'asset': return s('assetPath')
    case 'http': return s('url')
    case 'sftp': return present([s('host'), s('remotePath')]).join(':')
    case 'sql': return s('from')
    case 'push': return s('endpoint')
    default: return undefined
  }
}

function readStop (configuration: BackendConfiguration, t: Translate): BriefStop | undefined {
  const loader = configuration.loaderConfig
  const interpreter = configuration.interpreterConfig
  const value = present([
    named(t, 'data-importer.loader', loader?.type),
    named(t, 'data-importer.interpreter', interpreter?.type)
  ]).join(SEP)
  if (value === '') return undefined

  return {
    key: 'read',
    icon: INTERPRETER_ICON[interpreter?.type ?? ''] ?? 'import',
    role: `${T}.read`,
    value,
    note: origin(loader?.type, loader?.settings)
  }
}

function mapStop (configuration: BackendConfiguration, t: Translate): BriefStop | undefined {
  const rows = configuration.mappingConfig?.length ?? 0
  if (rows === 0) return undefined

  return { key: 'map', icon: 'many-to-many-relation', role: `${T}.map`, value: t(`${T}.mappings`, { count: rows }) }
}

/** what the import writes, and — as the note — where new elements land and whether they go live */
function writeStop (config: ResolverConfig | undefined, t: Translate): BriefStop | undefined {
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

  return { key: 'write', icon: 'data-object', role: `${T}.write`, value, note: note === '' ? undefined : note }
}

/** when it runs, and the switches that decide what happens to what the feed stops carrying */
function runStop (
  processing: ProcessingConfig | undefined,
  execution: ExecutionConfig | undefined,
  t: Translate
): BriefStop | undefined {
  const schedule = text(execution?.cronDefinition) ??
    (execution?.scheduleType === 'job' ? text(execution.scheduledAt) : undefined)
  const when = schedule ?? translated(t, 'data-importer.execution.manual-execution') ?? humanize('manualExecution')
  const value = named(t, 'data-importer.processing.execution-type', processing?.executionType) ?? when
  if (value === '') return undefined

  // the editor has two spellings for the delta switch; either means it is on
  const delta = processing?.doDeltaCheck === true ||
    (processing as { doDeltaCheckCheck?: boolean } | undefined)?.doDeltaCheckCheck === true
  const cleanup = processing?.cleanup?.doCleanup === true
    ? present([
        translated(t, 'data-importer.processing.cleanup.title') ?? humanize('cleanup'),
        named(t, 'data-importer.processing.cleanup.strategy', processing.cleanup.strategy)
      ]).join(': ')
    : undefined
  const note = present([
    value === when ? undefined : when,
    delta ? translated(t, 'data-importer.processing.delta-check') ?? humanize('deltaCheck') : undefined,
    cleanup
  ]).join(SEP)

  return { key: 'run', icon: 'play', role: `${T}.run`, value, note: note === '' ? undefined : note }
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

/** the editor's sections in the order it lays them out; mappings are the Map stop's business */
const GROUPS: Array<{ section: string, keys: Array<keyof BackendConfiguration>, skip?: ReadonlySet<string> }> = [
  { section: 'general', keys: ['general'], skip: NOT_A_SETTING },
  { section: 'dataSource', keys: ['loaderConfig', 'interpreterConfig'] },
  { section: 'resolver', keys: ['resolverConfig'] },
  { section: 'processing', keys: ['processingConfig'] },
  { section: 'execution', keys: ['executionConfig'] },
  { section: 'permissions', keys: ['permissions'] }
]

function groupsOf (configuration: BackendConfiguration): BriefGroup[] {
  return GROUPS
    .map(({ section, keys, skip }) => ({
      section,
      label: SECTION_LABELS[section] ?? section,
      count: keys.reduce<number>((sum, key) => sum + settingsIn(configuration[key], skip), 0)
    }))
    .filter((group) => group.count > 0)
}

/**
 * A configuration that does not exist yet, told once: every leaf of it is "added", so listing
 * them says only "all of it" at great length. The pipeline says what the thing does; the foot
 * says how much of it is waiting in the editor beside it.
 */
export function configBrief (configuration: BackendConfiguration, t: Translate): ConfigBrief {
  const stops = [
    readStop(configuration, t),
    mapStop(configuration, t),
    writeStop(configuration.resolverConfig, t),
    runStop(configuration.processingConfig, configuration.executionConfig, t)
  ].filter((stop): stop is BriefStop => stop !== undefined)

  const groups = groupsOf(configuration)

  return {
    name: text(configuration.general?.name) ?? '',
    active: configuration.general?.active === true,
    description: text(configuration.general?.description),
    stops,
    groups,
    total: groups.reduce((sum, group) => sum + group.count, 0)
  }
}
