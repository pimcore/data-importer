/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { buildFieldGroups } from './field-groups'
import { type MappingField } from './mapping-state'

const field = (key: string, group: string[]): MappingField =>
  ({ key, title: key, localized: false, mandatory: false, transformationResultType: 'default', takesColumnAsIs: true, group, classificationStore: false })

const toItem = (item: MappingField): { key: string, label: string, meta: MappingField } => ({ key: item.key, label: item.key.toUpperCase(), meta: item })

describe('the groups of the field picker', () => {
  it('nest the fields by their group path, in the order the fields come', () => {
    const power = field('technical.Engine.power', ['Technical', 'technical', 'Engine'])
    const groups = buildFieldGroups([
      field('sku', ['Base data']),
      field('price', ['Technical']),
      power,
      field('name', ['Base data'])
    ], toItem)

    expect(groups.map(({ label }) => label)).toEqual(['Base data', 'Technical'])
    expect(groups[0].items.map(({ label }) => label)).toEqual(['SKU', 'NAME'])
    expect(groups[1].items.map(({ key }) => key)).toEqual(['price'])
    expect(groups[1].children?.[0].label).toBe('technical')
    expect(groups[1].children?.[0].items).toEqual([])
    expect(groups[1].children?.[0].children?.[0]).toMatchObject({ label: 'Engine', items: [{ key: 'technical.Engine.power', meta: power }] })
  })

  it('give every group a key of its own', () => {
    const groups = buildFieldGroups([field('a', ['One', 'Two']), field('b', ['Two'])], toItem)
    const keys = [groups[0].key, groups[0].children![0].key, groups[1].key]

    expect(new Set(keys).size).toBe(3)
  })

  it('keep a field without a group findable', () => {
    expect(buildFieldGroups([field('loose', [])], toItem)).toEqual([{ key: 'group-0', label: '', children: [], items: [toItem(field('loose', []))] }])
  })
})
