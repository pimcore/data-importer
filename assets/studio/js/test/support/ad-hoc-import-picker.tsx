/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Test stand-in for the column picker of the ad hoc import
import React from 'react'

export interface PickerItem {
  key: string
  label: string
  disabled?: boolean
  meta?: unknown
}

export interface PickerGroup {
  key: string
  label: string
  items: PickerItem[]
  children?: PickerGroup[]
}

// a group of the column picker as a labelled list of its subgroups and items
export const PickerGroupView = ({ group, onSelect }: { group: PickerGroup, onSelect: (item: PickerItem) => void }): React.JSX.Element => (
  <fieldset aria-label={ group.label }>
    <ul>
      { (group.children ?? []).map((child) => (
        <li key={ child.key }>
          <PickerGroupView
            group={ child }
            onSelect={ onSelect }
          />
        </li>
      )) }
      { group.items.map((item) => (
        <li key={ item.key }>
          <button
            aria-selected={ false }
            disabled={ item.disabled }
            onClick={ () => { onSelect(item) } }
            role="treeitem"
            type="button"
          >
            { item.label }
          </button>
        </li>
      )) }
    </ul>
  </fieldset>
)
