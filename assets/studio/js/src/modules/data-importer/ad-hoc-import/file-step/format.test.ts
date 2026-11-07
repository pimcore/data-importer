/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { formatProgress, formatSize } from './format'

jest.mock('@pimcore/studio-ui-bundle/utils', () => ({
  formatDataUnit: (bytes: number, precision: number) => {
    if (bytes === 0) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1000)), units.length - 1)
    return `${(bytes / Math.pow(1000, power)).toFixed(precision)} ${units[power]}`
  }
}))

describe('formatSize', () => {
  it('shows whole KB and one decimal for MB', () => {
    expect(formatSize(84000)).toBe('84 KB')
    expect(formatSize(21400)).toBe('21 KB')
    expect(formatSize(2100000)).toBe('2.1 MB')
    expect(formatSize(0)).toBe('0 B')
  })
})

describe('formatProgress', () => {
  it('gives the loaded amount in the unit of the total', () => {
    expect(formatProgress(54000, 84000)).toEqual({ loaded: '54', total: '84 KB' })
    expect(formatProgress(1050000, 2100000)).toEqual({ loaded: '1.1', total: '2.1 MB' })
    expect(formatProgress(500, 900)).toEqual({ loaded: '500', total: '900 B' })
  })

  it('stands an empty file', () => {
    expect(formatProgress(0, 0)).toEqual({ loaded: '0', total: '0 B' })
  })
})
