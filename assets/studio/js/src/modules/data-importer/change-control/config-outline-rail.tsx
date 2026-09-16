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
import { ConfigSummary, type ConfigSummaryFoot, type ConfigSummarySection } from '@pimcore/data-hub'
import { type ConfigBrief } from './config-outline'

const T = 'data-importer.review.outline'

interface Props {
  readonly brief: ConfigBrief
}

/**
 * The rail for a configuration that does not exist yet. Every leaf of it is "added", so a list
 * of changes would say only "all of it" at great length: the summary tells what the pipeline
 * does instead, one stop per section, and the foot how much is waiting in the editor.
 */
export const ConfigBriefCard: React.FC<Props> = ({ brief }) => {
  const { t } = useTranslation()

  const stops: ConfigSummarySection[] = brief.stops.map((stop) => ({
    key: stop.key,
    label: t(stop.role),
    rows: [{ key: stop.key, label: stop.value, note: stop.note }]
  }))

  const foot: ConfigSummaryFoot | undefined = brief.groups.length === 0
    ? undefined
    : {
        lead: t(`${T}.settings`, { count: brief.total, sections: brief.groups.length }),
        detail: brief.groups.map((group) => `${t(group.label)} ${group.count}`).join(' · ')
      }

  return (
    <ConfigSummary
      active={ brief.active }
      description={ brief.description }
      foot={ foot }
      name={ brief.name }
      sections={ stops }
      variant="description"
    />
  )
}
