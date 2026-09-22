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
import { ConfigSummary, type ConfigSummaryFoot, type ConfigSummarySection } from '@pimcore/change-control-bundle/sdk'
import { type ConfigBrief } from './config-outline'

const T = 'data-importer.review.outline'

interface Props {
  readonly brief: ConfigBrief
  /** the section the editor is showing, so the reader knows where they are */
  readonly activeSection: string | undefined
  readonly onJump: (section: string) => void
}

/**
 * The rail for a configuration that does not exist yet. It reads as the change rail does —
 * the same sections, in the same order, opening the same steps — but a section that is all
 * new has nothing to mark field by field, so it says what it is set to instead.
 */
export const ConfigBriefCard: React.FC<Props> = ({ brief, activeSection, onJump }) => {
  const { t } = useTranslation()

  const sections: ConfigSummarySection[] = brief.sections.map((section) => ({
    key: section.key,
    label: t(section.label),
    rows: section.value === undefined
      ? []
      : [{ key: section.key, label: section.value, note: section.note }]
  }))

  // the sections each say how much they hold; the foot says how much that comes to
  const foot: ConfigSummaryFoot | undefined = brief.total === 0
    ? undefined
    : { lead: t(`${T}.settings`, { count: brief.filled, sections: brief.total }) }

  return (
    <ConfigSummary
      active={ brief.active }
      activeKey={ activeSection }
      description={ brief.description }
      foot={ foot }
      name={ brief.name }
      onOpenSection={ onJump }
      sections={ sections }
      variant="description"
    />
  )
}
