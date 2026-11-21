/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

export function isDropAllowed (
  droppedDataIndex: string | undefined,
  activeFilter: string | null
): boolean {
  if (droppedDataIndex === undefined) {
    return false
  }

  if (activeFilter === null) {
    return true
  }

  return droppedDataIndex === activeFilter
}
