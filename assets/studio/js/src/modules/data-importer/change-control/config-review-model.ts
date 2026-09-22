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
import { annotationKey, type FormAnnotations, type FormItemAnnotationStatus } from './studio-form-annotations'
import { mappingDiff, mappingRows } from './mapping-diff'
import { beforeOf, MAPPING_SLOT, isEqual, type ReviewMode, type ReviewPayload } from './review-payload'

export type { ReviewMode, ReviewPayload, ReviewSlot } from './review-payload'
export { beforeOf, isEqual, MAPPING_SLOT } from './review-payload'

export interface ConfigChange {
  /** document path — what the merge accepts as an exclude path */
  readonly address: string
  /** the same field as the editor's form binds it, when it binds it at all */
  readonly formPath?: string
  /** the document names no such field: nothing shows this change, and approving applies nothing */
  readonly unbound: boolean
  /** the field's own name, which is what a reader recognises */
  readonly label: string
  readonly section: string
  readonly status: FormItemAnnotationStatus
  readonly current: unknown
  readonly proposed: unknown
}

/** the general.* keys the editor's form lifts to its root; see ConfigurationPathMapper */
const FLATTENED = new Set(['active', 'description', 'group', 'name'])

/** identity and bookkeeping: the subject's, never a change a reviewer weighs */
const NOT_A_CHANGE = new Set(['general.name', 'general.type', 'general.path'])

/**
 * Every leaf the document names, by document path. The twin of the propose tool's own list
 * (ProposedImportConfiguration::KNOWN_PATHS) — change them together.
 */
const KNOWN_PATHS = new Set([
  'general.active', 'general.description', 'general.group',
  'general.name', 'general.path', 'general.type',
  'general.modificationDate', 'general.createDate', 'general.creationDate', 'general.writeable',
  'loaderConfig.type',
  'interpreterConfig.type',
  'resolverConfig.dataObjectClassId',
  'resolverConfig.elementType',
  'resolverConfig.loadingStrategy.type',
  'resolverConfig.createLocationStrategy.type',
  'resolverConfig.locationUpdateStrategy.type',
  'resolverConfig.publishingStrategy.type',
  'processingConfig.executionType',
  'processingConfig.idDataIndex',
  'processingConfig.doDeltaCheck',
  'processingConfig.doArchiveImportFile',
  'processingConfig.disableVersioning',
  'processingConfig.cleanup.doCleanup',
  'processingConfig.cleanup.strategy',
  'processingConfig.logging.disableInfoLogs',
  'processingConfig.logging.disableInfoFileObjects',
  'processingConfig.logging.disableErrorLogs',
  'processingConfig.logging.disableErrorFileObjects',
  'executionConfig.scheduleType',
  'executionConfig.cronDefinition',
  'executionConfig.scheduledAt'
])

/**
 * Whether the document names this path at all. A `settings` node is open — its keys belong to
 * the loader or strategy they configure, and the editor binds them per type — so anything
 * under one counts. A path that is neither is one nothing acts on: no field carries it and
 * the import never reads it, so a change to it would apply as nothing.
 */
export function isDocumentField (address: string): boolean {
  return KNOWN_PATHS.has(address) || address.split('.').includes('settings')
}

/**
 * A document path as the editor's form binds it, or undefined when the form has no field for
 * it — bookkeeping under `general`, most of all, which a reviewer must not be offered, and
 * any path the document does not name, which would otherwise file a mark no field reads.
 */
export function toFormPath (address: string): string | undefined {
  if (!isDocumentField(address)) return undefined

  const segments = address.split('.')
  if (segments[0] === 'general') {
    return segments.length > 1 && FLATTENED.has(segments[1])
      ? segments.slice(1).join('.')
      : undefined
  }
  return address
}

/** Un-flattens `a.b.c` leaves back into a nested object. */
function assign (target: Record<string, unknown>, address: string, value: unknown): void {
  const segments = address.split('.')
  let node = target
  segments.forEach((segment, index) => {
    if (index === segments.length - 1) {
      node[segment] = value
      return
    }
    if (typeof node[segment] !== 'object' || node[segment] === null) {
      node[segment] = {}
    }
    node = node[segment] as Record<string, unknown>
  })
}

/**
 * The configuration as the change set proposes it: the live document with the proposed leaves
 * laid over it.
 *
 * The live document has to come from the importer's own endpoint. A review payload carries
 * only the CHANGED subset of the state, which is the right thing for a diff and far too
 * little for an editor - mounted on it alone, every untouched select renders empty.
 */
export function proposedConfiguration (
  payload: ReviewPayload | undefined,
  live: BackendConfiguration | undefined,
  mode: ReviewMode = 'review'
): BackendConfiguration {
  const config: Record<string, unknown> = structuredClone(live ?? {}) as Record<string, unknown>
  if (payload == null) return config as BackendConfiguration

  for (const [slotKey, slot] of Object.entries(payload.slots ?? {})) {
    const proposed = slot.proposed ?? {}

    if (slotKey === MAPPING_SLOT) {
      // the mapping list rides one address, whole. The editor shows it in review order: a
      // dropped row stays where it was, marked, so the reader sees what stops being imported
      if (proposed.mappingConfig !== undefined) config.mappingConfig = mappingRows(mappingDiff(payload, mode))
      continue
    }

    for (const [address, value] of Object.entries(proposed)) assign(config, address, value)
  }

  return config as BackendConfiguration
}

/**
 * The mark on each mapping row, keyed the way the row header looks itself up. Rows bind no
 * Form.Item, so the mark sits on the row.
 */
export function mappingAnnotations (payload: ReviewPayload | undefined, mode: ReviewMode): FormAnnotations {
  const annotations: FormAnnotations = {}
  mappingDiff(payload, mode).forEach((entry, index) => {
    if (entry.status === 'unchanged') return
    annotations[annotationKey(['mappingConfig', index])] = { status: entry.status }
  })
  return annotations
}

/** what the editor calls each slot, as translation keys, in the order it shows them */
export const SECTION_LABELS: Record<string, string> = {
  general: 'data-importer.review.section.general',
  dataSource: 'data-importer.review.section.data-source',
  resolver: 'data-importer.review.section.resolver',
  processing: 'data-importer.review.section.processing',
  execution: 'data-importer.review.section.execution',
  permissions: 'data-importer.review.section.permissions'
}

/**
 * Where a section lives in the editor: which tab, and for Data Setup which step. This is
 * what lets the rail navigate rather than only list. Step 1 is Preview Import, which no
 * configuration value lands in.
 */
export const SECTION_TARGET: Record<string, { tab: string, step?: number }> = {
  general: { tab: 'general' },
  dataSource: { tab: 'data-setup', step: 0 },
  resolver: { tab: 'data-setup', step: 2 },
  mapping: { tab: 'data-setup', step: 3 },
  processing: { tab: 'data-setup', step: 4 },
  execution: { tab: 'execution' },
  permissions: { tab: 'permissions' }
}

export interface ChangeGroup {
  readonly section: string
  readonly label: string
  readonly changes: ConfigChange[]
}

/** Changes grouped the way the editor is laid out, so the rail reads as the form does. */
export function groupChanges (changes: ConfigChange[]): ChangeGroup[] {
  const bySection = new Map<string, ConfigChange[]>()
  for (const change of changes) {
    const list = bySection.get(change.section) ?? []
    list.push(change)
    bySection.set(change.section, list)
  }

  const ordered = [...Object.keys(SECTION_LABELS), ...bySection.keys()]
  const seen = new Set<string>()
  const groups: ChangeGroup[] = []
  for (const section of ordered) {
    if (seen.has(section)) continue
    seen.add(section)
    const list = bySection.get(section)
    if (list === undefined || list.length === 0) continue
    groups.push({ section, label: SECTION_LABELS[section] ?? section, changes: list })
  }

  return groups
}

/**
 * True when the change set CREATES the configuration rather than changing one. Nothing has a
 * previous value, so enumerating every field says only "all of it" at great length — and
 * withholding single leaves would land a configuration that was never reviewed as a whole.
 */
export function isNewConfiguration (payload: ReviewPayload | undefined, mode: ReviewMode = 'review'): boolean {
  const slots = Object.values(payload?.slots ?? {})
  if (slots.length === 0) return false

  return slots.every((slot) => Object.keys(beforeOf(slot, mode)).length === 0)
}

/** Every leaf the change set actually changes, in the order the editor shows the sections. */
export function configChanges (payload: ReviewPayload | undefined, mode: ReviewMode = 'review'): ConfigChange[] {
  if (payload == null) return []

  const changes: ConfigChange[] = []
  for (const [slotKey, slot] of Object.entries(payload.slots ?? {})) {
    if (slotKey === MAPPING_SLOT) continue

    const current = beforeOf(slot, mode)
    const proposed = slot.proposed ?? {}

    for (const [address, value] of Object.entries(proposed)) {
      if (NOT_A_CHANGE.has(address)) continue
      const before = current[address]
      if (isEqual(before, value)) continue
      changes.push({
        address,
        formPath: toFormPath(address),
        unbound: !isDocumentField(address),
        label: address.split('.').pop() ?? address,
        section: slotKey,
        status: before === undefined ? 'added' : 'changed',
        current: before,
        proposed: value
      })
    }
  }

  return changes
}

/** the review's marks on the fields the form binds: the status alone, the field says the rest */
export function annotationsFor (changes: ConfigChange[]): FormAnnotations {
  const annotations: FormAnnotations = {}
  for (const change of changes) {
    if (change.formPath === undefined) continue
    annotations[change.formPath] = { status: change.status }
  }
  return annotations
}

