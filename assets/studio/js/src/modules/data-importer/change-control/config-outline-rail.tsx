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
import { Tag } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type ConfigBrief } from './config-outline'
import { ProposalCard, StatePill, type CardNode } from './proposal-card'
import { useStyles } from './proposal-card.styles'
import { type useStyles as useSurfaceStyles } from './import-config-review-surface.styles'

const T = 'data-importer.review.outline'

/** the translation marks its own emphasis with **…**, so a translator decides what carries it */
const emphasised = (value: string): React.ReactNode[] =>
  value.split('**').map((part, index) => (
    index % 2 === 1 ? <b key={ `b${index}` }>{ part }</b> : <React.Fragment key={ `t${index}` }>{ part }</React.Fragment>
  ))

interface Props {
  readonly brief: ConfigBrief
  /** the surface still hands its own styles down; this card dresses itself */
  readonly styles: ReturnType<typeof useSurfaceStyles>['styles']
}

/**
 * The rail for a configuration that does not exist yet. Every leaf of it is "added", so a
 * list of changes would say only "all of it" at great length: the spine tells what the
 * pipeline does instead, and the foot how much is waiting in the editor.
 */
export const ConfigBriefCard: React.FC<Props> = ({ brief }) => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const nodes: CardNode[] = brief.stops.map((stop) => ({
    key: stop.key,
    body: (
      <>
        <div className={ styles.role }>{ t(stop.role) }</div>
        <div className={ styles.value }>{ stop.value }</div>
        { stop.note !== undefined && <div className={ styles.note }>{ stop.note }</div> }
      </>
    )
  }))

  const footer = brief.groups.length === 0
    ? undefined
    : (
      <div className={ styles.foot }>
        <div className={ styles.footLead }>
          { emphasised(t(`${T}.settings`, { count: brief.total, sections: brief.groups.length })) }
        </div>
        <div className={ styles.footGroups }>
          { brief.groups.map((group) => `${t(group.label)} ${group.count}`).join(' · ') }
        </div>
      </div>
      )

  return (
    <ProposalCard
      description={ brief.description }
      footer={ footer }
      name={ brief.name }
      nodes={ nodes }
      tags={
        <>
          <Tag
            color="green"
            style={ { marginInlineEnd: 0 } }
          >
            { t(`${T}.new`) }
          </Tag>
          <StatePill
            active={ brief.active }
            label={ t(`${T}.${brief.active ? 'active' : 'inactive'}`) }
          />
        </>
      }
    />
  )
}
