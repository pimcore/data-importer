/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type MappingField } from './mapping-state'

// one glyph per field kind, as the mapping board draws them: lines, globe, cube, grid
export const iconOf = (field: Pick<MappingField, 'key' | 'localized' | 'classificationStore'> | undefined): string => {
  if (field?.classificationStore === true) {
    return 'menu'
  }
  if (field?.key.includes('.') === true) {
    return 'data-object'
  }

  return field?.localized === true ? 'world' : 'long-text'
}
