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
import { ConfigSummary, type ConfigSummarySection } from '@pimcore/change-control-bundle/sdk'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import type { BackendConfiguration } from '../utils/transformers'
import { type ChangeGroup, type ConfigChange } from './config-review-model'
import { type MappingRowDiff } from './mapping-diff'

const T = 'data-importer.review'

/** the mapping step sits between the resolver and the processing settings in the editor */
const MAPPINGS_AFTER = 'resolver'

const text = (value: unknown): string | undefined =>
  typeof value === 'string' && value !== '' ? value : undefined

interface ListProps {
  readonly configuration: BackendConfiguration
  readonly groups: ChangeGroup[]
  readonly mappings: MappingRowDiff[]
  /** the section the editor currently shows, so the reader knows where they are */
  readonly activeSection: string | undefined
  readonly labelFor: (change: ConfigChange) => string
  readonly onJump: (section: string) => void
}

/**
 * The rail for a configuration that already exists. Data Hub draws the summary — every
 * configuration type's review shows the same one — so this is only the translation from
 * Change Control's shapes into its two.
 */
export const ChangeList: React.FC<ListProps> = ({
  configuration, groups, mappings, activeSection, labelFor, onJump
}) => {
  const { t } = useTranslation()

  const sections: ConfigSummarySection[] = groups.map((group) => ({
    key: group.section,
    label: t(group.label),
    rows: group.changes.map((change) => ({
      key: change.address,
      label: labelFor(change),
      hint: change.address,
      // the editor shows nothing for an unbound change, so the rail has to say it outright
      note: change.unbound ? t(`${T}.unbound`, { address: change.address }) : undefined,
      status: change.status
    }))
  }))

  // the mapping list rides one address, so it is a section of its own rather than a field
  const mappingSection: ConfigSummarySection | null = mappings.length === 0
    ? null
    : {
        key: 'mapping',
        label: t(`${T}.mappings`),
        rows: mappings.map((row) => ({
          key: row.key,
          label: row.label,
          status: row.status === 'unchanged' ? undefined : row.status
        }))
      }

  const at = groups.findIndex((group) => group.section === MAPPINGS_AFTER)
  const ordered = mappingSection === null
    ? sections
    : at === -1
      ? [...sections, mappingSection]
      : [...sections.slice(0, at + 1), mappingSection, ...sections.slice(at + 1)]

  return (
    <ConfigSummary
      activeKey={ activeSection }
      name={ text(configuration.general?.name) ?? '' }
      onOpenSection={ onJump }
      sections={ ordered }
    />
  )
}
