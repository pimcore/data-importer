/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type ColumnPickerGroup, type ColumnPickerItem } from '@pimcore/studio-ui-bundle/components'
import { type MappingField } from './mapping-state'

interface GroupNode {
  items: MappingField[]
  children: Map<string, GroupNode>
}

/**
 * The fields as groups of the column picker, nested by their group path as Studio's grid configuration nests its
 * columns (Studio's own `buildColumnPickerGroups` is not in the SDK this bundle builds against).
 */
export const buildFieldGroups = (
  fields: MappingField[],
  toItem: (field: MappingField) => ColumnPickerItem<MappingField>
): Array<ColumnPickerGroup<MappingField>> => {
  const root = new Map<string, GroupNode>()

  for (const field of fields) {
    // the server groups every field of a class it finds; one it could not stays findable
    const path = field.group.length > 0 ? field.group : ['']
    let level = root
    path.forEach((part, index) => {
      let node = level.get(part)
      if (node === undefined) {
        node = { items: [], children: new Map() }
        level.set(part, node)
      }
      if (index === path.length - 1) {
        node.items.push(field)
      }
      level = node.children
    })
  }

  let groupIndex = 0
  const convert = (level: Map<string, GroupNode>): Array<ColumnPickerGroup<MappingField>> =>
    [...level.entries()].map(([label, node]) => ({
      key: `group-${groupIndex++}`,
      label,
      children: convert(node.children),
      items: node.items.map(toItem)
    }))

  return convert(root)
}
