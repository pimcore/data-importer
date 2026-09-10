/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { Checkbox, Tag } from '@pimcore/studio-ui-bundle/components'
import { formatValue, type ChangeGroup, type ConfigChange } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

export const STATUS_COLOR: Record<string, string> = {
  added: 'success',
  changed: 'warning',
  removed: 'error',
  unchanged: 'default',
  moved: 'processing'
}

interface SectionProps {
  readonly group: ChangeGroup
  readonly collapsed: boolean
  readonly excluded: ReadonlySet<string>
  readonly onToggleCollapsed: () => void
  readonly onToggleExcluded: (addresses: string[], include: boolean) => void
  readonly styles: Styles
}

export const Section: React.FC<SectionProps> = ({
  group, collapsed, excluded, onToggleCollapsed, onToggleExcluded, styles
}) => {
  const addresses = group.changes.map((change) => change.address)
  const includedCount = addresses.filter((address) => !excluded.has(address)).length

  return (
    <div className={ styles.section }>
      <div className={ styles.sectionHead }>
        <Checkbox
          checked={ includedCount > 0 }
          indeterminate={ includedCount > 0 && includedCount < addresses.length }
          onChange={ (event) => { onToggleExcluded(addresses, event.target.checked) } }
        />
        <button
          className={ styles.sectionButton }
          onClick={ onToggleCollapsed }
          type="button"
        >
          <span className={ styles.caret }>{ collapsed ? '▸' : '▾' }</span>
          <span className={ styles.sectionLabel }>{ group.label }</span>
          <span className={ styles.count }>{ group.changes.length }</span>
        </button>
      </div>

      { !collapsed && group.changes.map((change) => (
        <div
          className={ styles.change }
          key={ change.address }
          title={ change.address }
        >
          <Checkbox
            checked={ !excluded.has(change.address) }
            onChange={ (event) => { onToggleExcluded([change.address], event.target.checked) } }
          />
          <span className={ styles.changeBody }>
            <span className={ styles.changeHead }>
              <span className={ styles.changeLabel }>{ leafOf(change) }</span>
              <Tag color={ STATUS_COLOR[change.status] }>{ change.status }</Tag>
            </span>
            <span className={ styles.changeValues }>
              { change.status !== 'added' && (
                <>
                  <span className={ styles.was }>{ formatValue(change.current) }</span>
                  <span>→</span>
                </>
              ) }
              <span className={ styles.now }>{ formatValue(change.proposed) }</span>
            </span>
          </span>
        </div>
      )) }
    </div>
  )
}

interface MappingSectionProps {
  readonly rows: MappingRowDiff[]
  readonly styles: Styles
}

export const MappingSection: React.FC<MappingSectionProps> = ({ rows, styles }) => (
  <div className={ styles.section }>
    <div className={ styles.sectionHead }>
      <span className={ styles.sectionSpacer } />
      <span className={ styles.sectionLabel }>Mappings</span>
      <span className={ styles.count }>{ rows.length }</span>
    </div>

    { rows.map((row) => (
      <div
        className={ styles.mapping }
        key={ row.key }
      >
        <span className={ styles.changeHead }>
          <span className={ styles.changeLabel }>{ row.label }</span>
          <Tag color={ STATUS_COLOR[row.status] }>{ row.status }</Tag>
        </span>
        <span className={ styles.changeValues }>
          { row.currentTarget !== undefined && row.currentTarget !== row.target && (
            <>
              <span className={ styles.was }>{ row.currentTarget }</span>
              <span>→</span>
            </>
          ) }
          <span className={ styles.now }>{ row.target }</span>
        </span>
      </div>
    )) }

    <div className={ styles.note }>
      Proposed as a whole — a single row cannot be withheld.
    </div>
  </div>
)

interface NewConfigurationSummaryProps {
  readonly name: string
  readonly settingCount: number
  readonly mappingCount: number
  readonly styles: Styles
}

/**
 * A create has no previous values, so listing every field says only "all of it" at length.
 * It is also all-or-nothing: withholding leaves would land a configuration nobody reviewed.
 */
export const NewConfigurationSummary: React.FC<NewConfigurationSummaryProps> = ({
  name, settingCount, mappingCount, styles
}) => (
  <div className={ styles.newConfig }>
    <div className={ styles.railHead }><span>New configuration</span></div>
    <div className={ styles.newName }>{ name }</div>
    <div className={ styles.newCounts }>
      <span>{ settingCount } settings</span>
      <span>·</span>
      <span>{ mappingCount } mappings</span>
    </div>
    <div className={ styles.note }>
      Nothing here replaces an existing value, so there is nothing to compare against — read it in
      the editor. A new configuration is approved or rejected whole.
    </div>
  </div>
)

function leafOf (change: ConfigChange): string {
  const segments = change.address.split('.')
  return segments[segments.length - 1]
}
