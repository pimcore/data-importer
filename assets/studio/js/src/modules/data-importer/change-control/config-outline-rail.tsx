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
import { type BriefStop, type ConfigBrief } from './config-outline'
import { useStyles } from './config-outline-rail.styles'
import { type useStyles as useSurfaceStyles } from './import-config-review-surface.styles'

const T = 'data-importer.review.outline'

const ICON = { width: 14, height: 14 }

interface Props {
  readonly brief: ConfigBrief
  /** the surface still hands its own styles down; this card dresses itself */
  readonly styles: ReturnType<typeof useSurfaceStyles>['styles']
}

/**
 * The rail for a configuration that does not exist yet: what it is called and whether it is
 * live, the pipeline as read → map → write → run, and a count of what is left to read in the
 * editor. Nothing here is a control: a create is approved or rejected whole.
 */
export const ConfigBriefCard: React.FC<Props> = ({ brief }) => {
  const { t } = useTranslation()
  const { styles, cx } = useStyles()

  const stop = (entry: BriefStop, index: number): React.JSX.Element => {
    const last = index === brief.stops.length - 1

    return (
      <React.Fragment key={ entry.key }>
        <div className={ styles.mark }>
          <span className={ styles.badge }>
            <Icon
              options={ ICON }
              value={ entry.icon }
            />
          </span>
          { !last && <span className={ styles.line } /> }
        </div>
        <div className={ last ? styles.stopLast : styles.stop }>
          <div className={ styles.role }>{ t(entry.role) }</div>
          <div className={ styles.value }>{ entry.value }</div>
          { entry.note !== undefined && <div className={ styles.note }>{ entry.note }</div> }
        </div>
      </React.Fragment>
    )
  }

  return (
    <div className={ styles.card }>
      <div className={ styles.head }>
        <div className={ styles.name }>
          <span className={ styles.nameText }>{ brief.name }</span>
          <span className={ styles.pill }>
            <span className={ cx(styles.dot, brief.active && styles.dotOn) } />
            { t(`${T}.${brief.active ? 'active' : 'inactive'}`) }
          </span>
        </div>
        { brief.description !== undefined && (
          <div className={ styles.description }>{ brief.description }</div>
        ) }
      </div>

      <div className={ styles.flow }>{ brief.stops.map(stop) }</div>

      { brief.groups.length > 0 && (
        <div className={ styles.foot }>
          <div className={ styles.footLead }>
            { t(`${T}.settings`, { count: brief.total, sections: brief.groups.length }) }
          </div>
          <div className={ styles.footGroups }>
            { brief.groups.map((group) => `${t(group.label)} ${group.count}`).join(' · ') }
          </div>
        </div>
      ) }
    </div>
  )
}
