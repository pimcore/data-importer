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
import type { FormAnnotations, FormItemAnnotationStatus } from './studio-form-annotations'

/** one slot of the review payload, as the change-set API ships it */
export interface ReviewSlot {
  readonly shape: string
  readonly proposed?: Record<string, unknown> | null
  readonly current?: Record<string, unknown> | null
  readonly base?: Record<string, unknown> | null
}

export interface ReviewPayload {
  readonly slots: Record<string, ReviewSlot>
  readonly meta?: { readonly label?: string, readonly changedFieldNames?: string[] }
}

export interface ConfigChange {
  /** document path — what the merge accepts as an exclude path */
  readonly address: string
  /** the same field as the editor's form binds it, when it binds it at all */
  readonly formPath?: string
  readonly section: string
  readonly status: FormItemAnnotationStatus
  readonly current: unknown
  readonly proposed: unknown
}

/** the general.* keys the editor's form lifts to its root; see ConfigurationPathMapper */
const FLATTENED = ['active', 'description', 'group', 'name']

const MAPPING_SLOT = 'mapping'

/**
 * A document path as the editor's form binds it, or undefined when the form has no field for
 * it — bookkeeping under `general`, most of all, which a reviewer must not be offered.
 */
export function toFormPath (address: string): string | undefined {
  const segments = address.split('.')
  if (segments[0] === 'general') {
    return segments.length > 1 && FLATTENED.includes(segments[1])
      ? segments.slice(1).join('.')
      : undefined
  }
  return address
}

const isEqual = (a: unknown, b: unknown): boolean => JSON.stringify(a ?? null) === JSON.stringify(b ?? null)

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
  live: BackendConfiguration | undefined
): BackendConfiguration {
  const config: Record<string, unknown> = structuredClone(live ?? {}) as Record<string, unknown>
  if (payload == null) return config as BackendConfiguration

  for (const [slotKey, slot] of Object.entries(payload.slots ?? {})) {
    const proposed = slot.proposed ?? {}

    if (slotKey === MAPPING_SLOT) {
      // the mapping list rides one address, whole
      const rows = proposed.mappingConfig
      if (rows !== undefined) config.mappingConfig = rows
      continue
    }

    for (const [address, value] of Object.entries(proposed)) assign(config, address, value)
  }

  return config as BackendConfiguration
}

/** Every leaf the change set actually changes, in the order the editor shows the sections. */
export function configChanges (payload: ReviewPayload | undefined): ConfigChange[] {
  if (payload == null) return []

  const changes: ConfigChange[] = []
  for (const [slotKey, slot] of Object.entries(payload.slots ?? {})) {
    if (slotKey === MAPPING_SLOT) continue

    const current = slot.current ?? {}
    const proposed = slot.proposed ?? {}

    for (const [address, value] of Object.entries(proposed)) {
      const before = current[address]
      if (isEqual(before, value)) continue
      changes.push({
        address,
        formPath: toFormPath(address),
        section: slotKey,
        status: before === undefined ? 'added' : 'changed',
        current: before,
        proposed: value
      })
    }
  }

  return changes
}

/** The mapping rows before and after, matched by mappingId where the rows carry one. */
export interface MappingRowDiff {
  readonly key: string
  readonly label: string
  readonly target: string
  readonly status: 'added' | 'removed' | 'changed' | 'unchanged'
  readonly currentTarget?: string
}

interface MappingRow {
  readonly mappingId?: string
  readonly label?: string
  readonly dataSourceIndex?: unknown
  readonly dataTarget?: { readonly type?: string, readonly settings?: Record<string, unknown> }
}

/**
 * Rows match on `mappingId` where both sides carry one. They do not always: the id is minted
 * in the Studio form, so a configuration written by the console has none, and a proposal that
 * adds ids would otherwise read as "every row removed, every row added". The natural key -
 * label plus source columns - catches those, and only genuinely new rows are left over.
 */
const naturalKey = (row: MappingRow): string =>
  `${String(row.label ?? '')}|${JSON.stringify(row.dataSourceIndex ?? null)}`

const rowLabel = (row: MappingRow): string => {
  if (typeof row.label === 'string' && row.label !== '') return row.label
  const source = row.dataSourceIndex
  if (Array.isArray(source)) return source.map(String).join(', ')
  return typeof source === 'string' ? source : '—'
}

const rowTarget = (row: MappingRow): string => {
  const field = row.dataTarget?.settings?.fieldName
  const type = row.dataTarget?.type ?? ''
  return typeof field === 'string' && field !== '' ? `${field} (${type})` : type
}

/** the row's own id, when it has one — never a positional stand-in */
const rowId = (row: MappingRow): string | undefined =>
  typeof row.mappingId === 'string' && row.mappingId !== '' ? row.mappingId : undefined

export function mappingDiff (payload: ReviewPayload | undefined): MappingRowDiff[] {
  const slot = payload?.slots?.[MAPPING_SLOT]
  if (slot == null) return []

  const proposed = (slot.proposed?.mappingConfig ?? []) as MappingRow[]
  const current = (slot.current?.mappingConfig ?? []) as MappingRow[]
  if (!Array.isArray(proposed) || !Array.isArray(current)) return []

  const byId = new Map<string, number>()
  const byNatural = new Map<string, number>()
  current.forEach((row, index) => {
    const id = rowId(row)
    if (id !== undefined) byId.set(id, index)
    if (!byNatural.has(naturalKey(row))) byNatural.set(naturalKey(row), index)
  })

  const matched = new Set<number>()
  const rows: MappingRowDiff[] = proposed.map((row, index) => {
    const id = rowId(row)
    const at = (id !== undefined ? byId.get(id) : undefined) ?? byNatural.get(naturalKey(row))
    const key = id ?? `#${index}`
    const target = rowTarget(row)

    if (at === undefined || matched.has(at)) {
      return { key, label: rowLabel(row), target, status: 'added' as const }
    }
    matched.add(at)

    const before = current[at]
    // the id itself is bookkeeping, not a change a reviewer should be shown
    const comparable = (candidate: MappingRow): MappingRow => {
      const { mappingId, ...rest } = candidate
      return rest as MappingRow
    }

    return {
      key,
      label: rowLabel(row),
      target,
      currentTarget: rowTarget(before),
      status: isEqual(comparable(before), comparable(row)) ? ('unchanged' as const) : ('changed' as const)
    }
  })

  // a row the proposal drops is only visible if it is put back where it was
  current.forEach((row, index) => {
    if (matched.has(index)) return
    rows.splice(Math.min(index, rows.length), 0, {
      key: rowId(row) ?? `current#${index}`,
      label: rowLabel(row),
      target: rowTarget(row),
      status: 'removed' as const
    })
  })

  return rows
}

export function annotationsFor (changes: ConfigChange[]): FormAnnotations {
  const annotations: FormAnnotations = {}
  for (const change of changes) {
    if (change.formPath === undefined) continue
    annotations[change.formPath] = {
      status: change.status,
      hint: change.status === 'added' ? undefined : formatValue(change.current)
    }
  }
  return annotations
}

export function formatValue (value: unknown): string {
  if (value === undefined || value === null || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'yes' : 'no'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
