/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { formatDataUnit } from '@pimcore/studio-ui-bundle/utils'

const KILO = 1000

// whole KB as long as they read well, one decimal above
export const formatSize = (bytes: number): string => formatDataUnit(bytes, bytes >= KILO * KILO ? 1 : 0)

/**
 * The amount loaded in the unit of the total: `54` of `84 KB`.
 */
export const formatProgress = (loaded: number, total: number): { loaded: string, total: string } => {
  const formattedTotal = formatSize(total)
  const power = total <= 0 ? 0 : Math.min(Math.floor(Math.log(total) / Math.log(KILO)), 4)
  const inUnit = loaded / Math.pow(KILO, power)

  return {
    loaded: inUnit.toFixed(total >= KILO * KILO ? 1 : 0),
    total: formattedTotal
  }
}
