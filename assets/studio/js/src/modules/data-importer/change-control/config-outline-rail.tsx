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
import { type BriefStop, type ConfigBrief } from './config-outline'
import { type useStyles } from './import-config-review-surface.styles'

type Styles = ReturnType<typeof useStyles>['styles']

const Stop: React.FC<{ readonly stop: BriefStop, readonly styles: Styles }> = ({ stop, styles }) => (
  <div className={ styles.briefStop }>
    <span className={ styles.briefIcon }>
      <Icon
        options={ { width: 16, height: 16 } }
        value={ stop.icon }
      />
    </span>
    <span className={ styles.briefText }>
      <span className={ styles.briefLabel }>{ stop.label }</span>
      { stop.note !== undefined && <span className={ styles.briefNote }>{ stop.note }</span> }
    </span>
  </div>
)

/**
 * The rail for a configuration that does not exist yet: the pipeline in one glance — where
 * the data comes from, what it becomes — and how it runs, as tags. No sections, no fields:
 * the editor beside it has all of those.
 */
export const ConfigBriefCard: React.FC<{ readonly brief: ConfigBrief, readonly styles: Styles }> = ({ brief, styles }) => (
  <div className={ styles.brief }>
    <div className={ styles.briefFlow }>
      <Stop
        stop={ brief.source }
        styles={ styles }
      />
      <span className={ styles.briefConnector } />
      <Stop
        stop={ brief.target }
        styles={ styles }
      />
    </div>
    <div className={ styles.briefTags }>
      { brief.tags.map((tag) => (
        <Tag
          bordered={ false }
          color={ tag.colour }
          key={ tag.key }
          style={ { marginInlineEnd: 0 } }
        >
          { tag.label }
        </Tag>
      )) }
    </div>
  </div>
)
