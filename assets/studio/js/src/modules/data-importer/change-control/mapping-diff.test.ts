/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { mappingDiff, mappingRows } from './mapping-diff'
import type { ReviewPayload } from './review-payload'

const row = (mappingId: string | undefined, label: string, fieldName: string, source: unknown = [label]): Record<string, unknown> => ({
  ...(mappingId === undefined ? {} : { mappingId }),
  label,
  dataSourceIndex: source,
  dataTarget: { type: 'direct', settings: { fieldName } }
})

const payload = (current: unknown[], proposed: unknown[], base: unknown[] = current): ReviewPayload => ({
  slots: {
    mapping: {
      shape: 'record',
      current: { mappingConfig: current },
      proposed: { mappingConfig: proposed },
      base: { mappingConfig: base }
    }
  }
})

describe('mappingDiff', () => {
  it('marks a row whose target changed, matched by its id, and keeps the target it had', () => {
    const diff = mappingDiff(payload(
      [row('a', 'VIN', 'vin'), row('b', 'Color', 'color')],
      [row('a', 'VIN', 'vin'), row('b', 'Color', 'paint')]
    ))

    expect(diff.map((entry) => entry.status)).toEqual(['unchanged', 'changed'])
    expect(diff[1]).toMatchObject({ key: 'b', target: 'paint (direct)', currentTarget: 'color (direct)' })
  })

  it('puts a dropped row back where it was, so the reviewer sees what stops being imported', () => {
    const diff = mappingDiff(payload(
      [row('a', 'VIN', 'vin'), row('b', 'Color', 'color'), row('c', 'Price', 'price')],
      [row('a', 'VIN', 'vin'), row('c', 'Price', 'price'), row('d', 'Model', 'model')]
    ))

    expect(diff.map((entry) => [entry.key, entry.status])).toEqual([
      ['a', 'unchanged'],
      ['b', 'removed'],
      ['c', 'unchanged'],
      ['d', 'added']
    ])
    expect(mappingRows(diff).map((r) => r.label)).toEqual(['VIN', 'Color', 'Price', 'Model'])
  })

  it('does not read ids a proposal adds as every row replaced', () => {
    const diff = mappingDiff(payload(
      [row(undefined, 'VIN', 'vin')],
      [row('minted', 'VIN', 'vin')]
    ))

    expect(diff.map((entry) => entry.status)).toEqual(['unchanged'])
  })

  it('reads a relabelled row as changed through its source and target', () => {
    const diff = mappingDiff(payload(
      [row(undefined, 'VIN', 'vin', ['0'])],
      [row(undefined, 'Vehicle id', 'vin', ['0'])]
    ))

    expect(diff.map((entry) => entry.status)).toEqual(['changed'])
  })

  it('reads a decided change set against the base it was recorded on', () => {
    const merged = [row('a', 'VIN', 'chassis')]
    const diff = mappingDiff(payload(merged, merged, [row('a', 'VIN', 'vin')]), 'history')

    expect(diff.map((entry) => entry.status)).toEqual(['changed'])
  })

  it('is empty without a mapping slot', () => {
    expect(mappingDiff({ slots: {} })).toEqual([])
    expect(mappingDiff(undefined)).toEqual([])
  })
})
