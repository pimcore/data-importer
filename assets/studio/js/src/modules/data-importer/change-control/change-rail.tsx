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
import { Icon } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ChangeGroup, type ConfigChange } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'
import { StatusTag } from './status-tag'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const T = 'data-importer.review'

/** the mapping step sits between the resolver and the processing settings in the editor */
const MAPPINGS_AFTER = 'resolver'

interface HeadlineProps {
  readonly label: string
  readonly active: boolean
  readonly onJump: () => void
  readonly styles: Styles
}

/** A section's name, and the one control in the rail: the whole line opens it in the editor. */
const Headline: React.FC<HeadlineProps> = ({ label, active, onJump, styles }) => {
  const { t } = useTranslation()

  return (
    <button
      className={ active ? styles.headlineActive : styles.headline }
      onClick={ onJump }
      title={ t(`${T}.open-section`) }
      type="button"
    >
      <span>{ label }</span>
      <Icon
        options={ { width: 14, height: 14 } }
        value="arrow-narrow-right"
      />
    </button>
  )
}

interface ListProps {
  readonly groups: ChangeGroup[]
  readonly mappings: MappingRowDiff[]
  /** the section the editor currently shows, so the reader knows where they are */
  readonly activeSection: string | undefined
  readonly labelFor: (change: ConfigChange) => string
  readonly onJump: (section: string) => void
  readonly styles: Styles
}

/**
 * A map of the change, in the order the editor lays it out: one headline per section that
 * opens it in the editor, and under it what changed there — a listing, not controls. Nothing
 * here is decided; the change set is approved whole.
 */
export const ChangeList: React.FC<ListProps> = ({ groups, mappings, activeSection, labelFor, onJump, styles }) => {
  const { t } = useTranslation()

  const mappingGroup = mappings.length === 0
    ? null
    : (
      <div
        className={ styles.group }
        key="mapping"
      >
        <Headline
          active={ activeSection === 'mapping' }
          label={ t(`${T}.mappings`) }
          onJump={ () => { onJump('mapping') } }
          styles={ styles }
        />
        <ul className={ styles.items }>
          { mappings.map((row) => (
            <li
              className={ styles.item }
              key={ row.key }
            >
              <span className={ styles.itemLabel }>{ row.label }</span>
              { row.status !== 'unchanged' && <StatusTag status={ row.status } /> }
            </li>
          )) }
        </ul>
      </div>
      )

  const sections = groups.map((group) => (
    <div
      className={ styles.group }
      key={ group.section }
    >
      <Headline
        active={ activeSection === group.section }
        label={ t(group.label) }
        onJump={ () => { onJump(group.section) } }
        styles={ styles }
      />
      <ul className={ styles.items }>
        { group.changes.map((change) => (
          <li
            className={ styles.item }
            key={ change.address }
            title={ change.address }
          >
            <span className={ styles.itemLabel }>{ labelFor(change) }</span>
            <StatusTag status={ change.status } />
          </li>
        )) }
      </ul>
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
      <div className={ styles.headlineStatic }>{ t(`${T}.new.title`) }</div>
      <div className={ styles.newName }>{ name }</div>
      <div className={ styles.note }>
        { t(`${T}.new.settings`, { count: settingCount }) } · { t(`${T}.new.mappings`, { count: mappingCount }) }
      </div>
      <div className={ styles.note }>{ t(`${T}.new.note`) }</div>
    </div>
  )
}
