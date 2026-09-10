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
import { Checkbox, Input, Tag } from '@pimcore/studio-ui-bundle/components'
import { formatValue, type ConfigChange, type TabGroup } from './config-review-model'
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

export const STATE_FILTERS = ['All', 'Changed', 'Added', 'Removed'] as const

export type StateFilter = typeof STATE_FILTERS[number]

interface HeadProps {
  readonly changed: number
  readonly added: number
  readonly removed: number
  readonly tabsTouched: number
  readonly tabCount: number
  readonly styles: Styles
}

/** What the change set is, before what is in it. */
export const RailHead: React.FC<HeadProps> = ({ changed, added, removed, tabsTouched, tabCount, styles }) => (
  <div className={ styles.railTop }>
    <div className={ styles.summaryLine }>
      { changed + added + removed } changes · { tabsTouched } of { tabCount } tabs affected
    </div>
    <div className={ styles.pills }>
      <Tag color="warning">{ changed } changed</Tag>
      <Tag color="success">{ added } added</Tag>
      <Tag color="error">{ removed } removed</Tag>
    </div>
  </div>
)

interface FiltersProps {
  readonly filter: StateFilter
  readonly query: string
  readonly onFilter: (filter: StateFilter) => void
  readonly onQuery: (query: string) => void
  readonly styles: Styles
}

export const RailFilters: React.FC<FiltersProps> = ({ filter, query, onFilter, onQuery, styles }) => (
  <>
    <div
      aria-label="Filter by state"
      className={ styles.chips }
      role="group"
    >
      { STATE_FILTERS.map((option) => (
        <button
          aria-pressed={ filter === option }
          className={ styles.chip }
          key={ option }
          onClick={ () => { onFilter(option) } }
          type="button"
        >
          { option }
        </button>
      )) }
    </div>
    <div className={ styles.search }>
      <Input
        onChange={ (event) => { onQuery(event.target.value) } }
        placeholder="Find a field"
        size="small"
        value={ query }
      />
    </div>
  </>
)

interface TreeProps {
  readonly tabs: TabGroup[]
  readonly excluded: ReadonlySet<string>
  readonly collapsed: ReadonlySet<string>
  readonly activeTab: string
  readonly target: string | null
  readonly onToggleCollapsed: (tab: string) => void
  readonly onToggleExcluded: (addresses: string[], include: boolean) => void
  readonly onJump: (change: ConfigChange) => void
  readonly styles: Styles
}

/**
 * Tab → section → field. A row is a place in the editor, not only an entry in a list: the
 * label carries the reader there, and the checkbox decides whether it lands.
 */
export const ChangeTree: React.FC<TreeProps> = ({
  tabs, excluded, collapsed, activeTab, target, onToggleCollapsed, onToggleExcluded, onJump, styles
}) => {
  if (tabs.length === 0) {
    return <div className={ styles.state }>No fields match your search.</div>
  }

  return (
    <>
      { tabs.map((tab) => {
        const addresses = tab.groups.reduce<string[]>(
          (all, group) => [...all, ...group.changes.map((change) => change.address)], [])
        const included = addresses.filter((address) => !excluded.has(address)).length
        const isOpen = !collapsed.has(tab.tab)

        return (
          <div
            className={ styles.section }
            key={ tab.tab }
          >
            <div className={ styles.sectionHead }>
              <Checkbox
                checked={ included > 0 }
                indeterminate={ included > 0 && included < addresses.length }
                onChange={ (event) => { onToggleExcluded(addresses, event.target.checked) } }
              />
              <button
                className={ styles.sectionButton }
                onClick={ () => { onToggleCollapsed(tab.tab) } }
                type="button"
              >
                <span className={ styles.caret }>{ isOpen ? '▾' : '▸' }</span>
                <span className={ activeTab === tab.tab ? styles.groupLabelActive : styles.sectionLabelText }>
                  { tab.label }
                </span>
                <span className={ styles.count }>{ tab.count }</span>
              </button>
            </div>

            { isOpen && tab.groups.map((group) => (
              <div key={ group.section }>
                <div className={ styles.sectionLabel }>{ group.label }</div>
                { group.changes.map((change) => (
                  <div
                    className={ target === change.address ? styles.rowTarget : styles.row }
                    key={ change.address }
                  >
                    <Checkbox
                      checked={ !excluded.has(change.address) }
                      disabled={ change.locked }
                      onChange={ (event) => { onToggleExcluded([change.address], event.target.checked) } }
                    />
                    <button
                      className={ styles.rowLabel }
                      onClick={ () => { onJump(change) } }
                      title={ change.address }
                      type="button"
                    >
                      { change.label }
                    </button>
                    <Tag color={ change.locked ? 'default' : STATUS_COLOR[change.status] }>
                      { change.locked ? 'whole' : change.status }
                    </Tag>
                  </div>
                )) }
              </div>
            )) }
          </div>
        )
      }) }
    </>
  )
}

interface MappingProps {
  readonly rows: MappingRowDiff[]
  readonly onJump: () => void
  readonly styles: Styles
}

/** The mapping list is one address, so it is one entry that carries you to the step. */
export const MappingSection: React.FC<MappingProps> = ({ rows, onJump, styles }) => (
  <div className={ styles.section }>
    <div className={ styles.sectionHead }>
      <span className={ styles.sectionSpacer } />
      <button
        className={ styles.sectionButton }
        onClick={ onJump }
        type="button"
      >
        <span className={ styles.caret } />
        <span className={ styles.sectionLabelText }>Mappings</span>
        <span className={ styles.count }>{ rows.length }</span>
      </button>
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

    <div className={ styles.note }>Proposed as a whole — a single row cannot be withheld.</div>
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

/** the value shown beside a change, kept to one line by the rail's styles */
export const changePreview = (change: ConfigChange): string =>
  change.status === 'added'
    ? formatValue(change.proposed)
    : `${formatValue(change.current)} → ${formatValue(change.proposed)}`
