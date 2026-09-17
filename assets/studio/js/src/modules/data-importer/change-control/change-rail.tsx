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
import { Icon, Tag } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import type { BackendConfiguration } from '../utils/transformers'
import { type ChangeGroup, type ConfigChange } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'
import { StatusTag } from './status-tag'
import { ProposalCard, type CardNode } from './proposal-card'
import { useStyles } from './proposal-card.styles'
import { type useStyles as useSurfaceStyles } from './import-config-review-surface.styles'

const T = 'data-importer.review'

const ARROW = { width: 12, height: 12 }

/** the mapping step sits between the resolver and the processing settings in the editor */
const MAPPINGS_AFTER = 'resolver'

interface ListProps {
  readonly configuration: BackendConfiguration
  readonly count: number
  readonly groups: ChangeGroup[]
  readonly mappings: MappingRowDiff[]
  /** the section the editor currently shows, so the reader knows where they are */
  readonly activeSection: string | undefined
  readonly labelFor: (change: ConfigChange) => string
  readonly onJump: (section: string) => void
  /** the surface still hands its own styles down; this card dresses itself */
  readonly styles: ReturnType<typeof useSurfaceStyles>['styles']
}

/**
 * The rail for a configuration that already exists: a map of the change in the order the
 * editor lays it out, one node per section that moved. A section's label opens it in the
 * editor; the fields under it are a listing, because a change set is approved whole.
 */
export const ChangeList: React.FC<ListProps> = ({
  configuration, count, groups, mappings, activeSection, labelFor, onJump
}) => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const section = (key: string, label: string, rows: React.ReactNode): React.ReactNode => (
    <>
      <button
        className={ styles.section }
        onClick={ () => { onJump(key) } }
        title={ t(`${T}.open-section`) }
        type="button"
      >
        <span>{ label }</span>
        <Icon
          options={ ARROW }
          value="arrow-narrow-right"
        />
      </button>
      { rows }
    </>
  )

  const sectionNodes: CardNode[] = groups.map((group) => ({
    key: group.section,
    muted: activeSection !== group.section,
    body: section(group.section, t(group.label), group.changes.map((change) => (
      <div
        className={ styles.field }
        key={ change.address }
        title={ change.address }
      >
        <span className={ styles.fieldLabel }>{ labelFor(change) }</span>
        <StatusTag status={ change.status } />
      </div>
    )))
  }))

  const mappingNode: CardNode | null = mappings.length === 0
    ? null
    : {
        key: 'mapping',
        muted: activeSection !== 'mapping',
        body: section('mapping', t(`${T}.mappings`), mappings.map((row) => (
          <div
            className={ styles.field }
            key={ row.key }
          >
            <span className={ styles.fieldLabel }>{ row.label }</span>
            { row.status !== 'unchanged' && <StatusTag status={ row.status } /> }
          </div>
        )))
      }

  const at = groups.findIndex((group) => group.section === MAPPINGS_AFTER)
  const nodes = mappingNode === null
    ? sectionNodes
    : at === -1
      ? [...sectionNodes, mappingNode]
      : [...sectionNodes.slice(0, at + 1), mappingNode, ...sectionNodes.slice(at + 1)]

  return (
    <ProposalCard
      description={ typeof configuration.general?.description === 'string' && configuration.general.description !== ''
        ? configuration.general.description
        : undefined }
      name={ typeof configuration.general?.name === 'string' ? configuration.general.name : '' }
      nodes={ nodes }
      tags={
        <Tag
          color="gold"
          style={ { marginInlineEnd: 0 } }
        >
          { t(`${T}.changes`, { count }) }
        </Tag>
      }
    />
  )
}
