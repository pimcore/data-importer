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
import { Checkbox } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ConfigChange, type TabGroup } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'
import { StatusTag } from './status-tag'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const T = 'data-importer.review'

interface TreeProps {
  readonly tabs: TabGroup[]
  readonly excluded: ReadonlySet<string>
  readonly activeTab: string
  readonly target: string | null
  /** absent once the change set is resolved: nothing is left to decide */
  readonly onToggleExcluded?: (addresses: string[], include: boolean) => void
  readonly onJump: (change: ConfigChange) => void
  readonly styles: Styles
}

/**
 * Tab → field, in the order the editor lays them out. A row is a place in the editor, not
 * only an entry in a list: the label carries the reader there, the checkbox decides whether
 * it lands. A tab that holds several sections names them; one that is its own section does not.
 */
export const ChangeTree: React.FC<TreeProps> = ({
  tabs, excluded, activeTab, target, onToggleExcluded, onJump, styles
}) => {
  const { t } = useTranslation()

  return (
    <>
      { tabs.map((tab) => {
        const addresses = tab.groups.reduce<string[]>(
          (all, group) => [...all, ...group.changes.map((change) => change.address)], [])
        const included = addresses.filter((address) => !excluded.has(address)).length
        const first = tab.groups[0]?.changes[0]

        return (
          <div
            className={ styles.group }
            key={ tab.tab }
          >
            <div className={ styles.groupHead }>
              { onToggleExcluded !== undefined && (
                <Checkbox
                  checked={ included > 0 }
                  indeterminate={ included > 0 && included < addresses.length }
                  onChange={ (event) => { onToggleExcluded(addresses, event.target.checked) } }
                />
              ) }
              <button
                className={ activeTab === tab.tab ? styles.groupLabelActive : styles.groupLabel }
                onClick={ () => { if (first !== undefined) onJump(first) } }
                type="button"
              >
                { t(tab.label) }
              </button>
              <span className={ styles.count }>{ tab.count }</span>
            </div>

            { tab.groups.map((group) => (
              <React.Fragment key={ group.section }>
                { tab.groups.length > 1 && <div className={ styles.sectionLabel }>{ t(group.label) }</div> }
                { group.changes.map((change) => (
                  <div
                    className={ target === change.address ? styles.rowTarget : styles.row }
                    key={ change.address }
                  >
                    { onToggleExcluded !== undefined && (
                      <Checkbox
                        checked={ !excluded.has(change.address) }
                        disabled={ change.locked }
                        onChange={ (event) => { onToggleExcluded([change.address], event.target.checked) } }
                      />
                    ) }
                    <button
                      className={ styles.rowLabel }
                      onClick={ () => { onJump(change) } }
                      title={ change.address }
                      type="button"
                    >
                      { change.label }
                    </button>
                    <StatusTag status={ change.locked ? 'whole' : change.status } />
                  </div>
                )) }
              </React.Fragment>
            )) }
          </div>
        )
      }) }
    </>
  )
}

interface MappingProps {
  readonly rows: MappingRowDiff[]
  readonly active: boolean
  readonly onJump: () => void
  readonly styles: Styles
}

/** The mapping list is one address, so it is one entry that carries you to the step. */
export const MappingSection: React.FC<MappingProps> = ({ rows, active, onJump, styles }) => {
  const { t } = useTranslation()

  return (
    <div className={ styles.group }>
      <div className={ styles.groupHead }>
        <button
          className={ active ? styles.groupLabelActive : styles.groupLabel }
          onClick={ onJump }
          type="button"
        >
          { t(`${T}.mappings`) }
        </button>
        <span className={ styles.count }>{ rows.length }</span>
      </div>

      { rows.map((row) => (
        <div
          className={ styles.mappingRow }
          key={ row.key }
        >
          <button
            className={ styles.rowLabel }
            onClick={ onJump }
            type="button"
          >
            { row.label }
          </button>
          { row.status !== 'unchanged' && <StatusTag status={ row.status } /> }
          <div className={ styles.rowMeta }>
            { row.currentTarget !== undefined && row.currentTarget !== row.target && (
              <><span className={ styles.was }>{ row.currentTarget }</span><span> → </span></>
            ) }
            <span>{ row.target }</span>
          </div>
        </div>
      )) }

      <div className={ styles.note }>{ t(`${T}.mappings-whole`) }</div>
    </div>
  )
}

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
}) => {
  const { t } = useTranslation()

  return (
    <div className={ styles.group }>
      <div className={ styles.caption }>{ t(`${T}.new.title`) }</div>
      <div className={ styles.newName }>{ name }</div>
      <div className={ styles.rowMeta }>
        { t(`${T}.new.settings`, { count: settingCount }) } · { t(`${T}.new.mappings`, { count: mappingCount }) }
      </div>
      <div className={ styles.note }>{ t(`${T}.new.note`) }</div>
    </div>
  )
}
