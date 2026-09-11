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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ChangeGroup, type ConfigChange } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'
import { StatusTag } from './status-tag'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const T = 'data-importer.review'

/** the mapping step sits between the resolver and the processing settings in the editor */
const MAPPINGS_AFTER = 'resolver'

interface ListProps {
  readonly groups: ChangeGroup[]
  readonly mappings: MappingRowDiff[]
  /** the section the editor currently shows, so the reader knows where they are */
  readonly activeSection: string | undefined
  readonly target: string | null
  readonly labelFor: (change: ConfigChange) => string
  readonly onJump: (change: ConfigChange) => void
  readonly onJumpMappings: () => void
  readonly styles: Styles
}

/**
 * A map of the change, in the order the editor lays it out: one caption per section, one row
 * per changed field. A row is a place — its label carries the reader to the field, the mark
 * says what happened there. Nothing here is decided; the change set is approved whole.
 */
export const ChangeList: React.FC<ListProps> = ({
  groups, mappings, activeSection, target, labelFor, onJump, onJumpMappings, styles
}) => {
  const { t } = useTranslation()

  const mappingGroup = mappings.length === 0
    ? null
    : (
      <div
        className={ styles.group }
        key="mapping"
      >
        <button
          className={ activeSection === 'mapping' ? styles.captionActive : styles.caption }
          onClick={ onJumpMappings }
          type="button"
        >
          { t(`${T}.mappings`) }
        </button>
        { mappings.map((row) => (
          <button
            className={ styles.row }
            key={ row.key }
            onClick={ onJumpMappings }
            type="button"
          >
            <span className={ styles.rowLabel }>{ row.label }</span>
            { row.status !== 'unchanged' && <StatusTag status={ row.status } /> }
          </button>
        )) }
      </div>
      )

  const sections = groups.map((group) => (
    <div
      className={ styles.group }
      key={ group.section }
    >
      <button
        className={ activeSection === group.section ? styles.captionActive : styles.caption }
        onClick={ () => { onJump(group.changes[0]) } }
        type="button"
      >
        { t(group.label) }
      </button>
      { group.changes.map((change) => (
        <button
          className={ target === change.address ? styles.rowTarget : styles.row }
          key={ change.address }
          onClick={ () => { onJump(change) } }
          title={ change.address }
          type="button"
        >
          <span className={ styles.rowLabel }>{ labelFor(change) }</span>
          <StatusTag status={ change.status } />
        </button>
      )) }
    </div>
  ))

  const at = groups.findIndex((group) => group.section === MAPPINGS_AFTER)
  const ordered = mappingGroup === null
    ? sections
    : at === -1
      ? [...sections, mappingGroup]
      : [...sections.slice(0, at + 1), mappingGroup, ...sections.slice(at + 1)]

  return <>{ ordered }</>
}

interface NewConfigurationSummaryProps {
  readonly name: string
  readonly settingCount: number
  readonly mappingCount: number
  readonly styles: Styles
}

/**
 * A create has no previous values, so listing every field says only "all of it" at length.
 */
export const NewConfigurationSummary: React.FC<NewConfigurationSummaryProps> = ({
  name, settingCount, mappingCount, styles
}) => {
  const { t } = useTranslation()

  return (
    <div className={ styles.group }>
      <div className={ styles.caption }>{ t(`${T}.new.title`) }</div>
      <div className={ styles.newName }>{ name }</div>
      <div className={ styles.note }>
        { t(`${T}.new.settings`, { count: settingCount }) } · { t(`${T}.new.mappings`, { count: mappingCount }) }
      </div>
      <div className={ styles.note }>{ t(`${T}.new.note`) }</div>
    </div>
  )
}
