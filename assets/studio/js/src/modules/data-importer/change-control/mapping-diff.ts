/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { beforeOf, isEqual, MAPPING_SLOT, type ReviewMode, type ReviewPayload } from './review-payload'

/** The mapping rows before and after, matched by mappingId where the rows carry one. */
export interface MappingRowDiff {
  readonly key: string
  readonly label: string
  readonly target: string
  readonly status: 'added' | 'removed' | 'changed' | 'unchanged'
  readonly currentTarget?: string
  /** the row as the editor should show it: the proposed one, or the dropped one it stands in for */
  readonly row: MappingRow
}

export interface MappingRow {
  readonly mappingId?: string
  readonly label?: string
  readonly dataSourceIndex?: unknown
  readonly dataTarget?: { readonly type?: string, readonly settings?: Record<string, unknown> }
}

/** the order the rows are shown in: proposed rows as proposed, a dropped row where it was */
export const mappingRows = (diff: MappingRowDiff[]): MappingRow[] => diff.map((entry) => entry.row)

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

export function mappingDiff (payload: ReviewPayload | undefined, mode: ReviewMode = 'review'): MappingRowDiff[] {
  const slot = payload?.slots?.[MAPPING_SLOT]
  if (slot == null) return []

  const proposed = (slot.proposed?.mappingConfig ?? []) as MappingRow[]
  const current = (beforeOf(slot, mode).mappingConfig ?? []) as MappingRow[]
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
      return { key, label: rowLabel(row), target, status: 'added' as const, row }
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
      status: isEqual(comparable(before), comparable(row)) ? ('unchanged' as const) : ('changed' as const),
      row
    }
  })

  // a row the proposal drops is only visible if it is put back where it was
  current.forEach((row, index) => {
    if (matched.has(index)) return
    rows.splice(Math.min(index, rows.length), 0, {
      key: rowId(row) ?? `current#${index}`,
      label: rowLabel(row),
      target: rowTarget(row),
      status: 'removed' as const,
      row
    })
  })

  return rows
}
