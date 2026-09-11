/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

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

/** review: what is proposed against the live document; history: what was recorded against its base */
export type ReviewMode = 'review' | 'history'

export const MAPPING_SLOT = 'mapping'

export const isEqual = (a: unknown, b: unknown): boolean => JSON.stringify(a ?? null) === JSON.stringify(b ?? null)

/**
 * The side a change is read against. While a change set is open that is the live document,
 * so a reviewer sees what approving would do now. Once it is resolved the live document has
 * moved on - after a merge it IS the proposal - and only the base it was recorded against
 * still says what changed.
 */
export const beforeOf = (slot: ReviewSlot, mode: ReviewMode): Record<string, unknown> =>
  (mode === 'history' ? slot.base : slot.current) ?? {}
