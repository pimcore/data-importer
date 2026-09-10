/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo, useState } from 'react'
import { DataImporterConfigEditor } from '../components/data-importer-config-editor'
import { useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import type { BackendConfiguration } from '../utils/transformers'
import { FormAnnotationsProvider } from './studio-form-annotations'
import {
  annotationsFor, configChanges, groupChanges, isNewConfiguration, proposedConfiguration,
  type ReviewPayload
} from './config-review-model'
import { mappingDiff } from './mapping-diff'
import { MappingSection, NewConfigurationSummary, Section } from './change-rail'
import { useStyles } from './import-config-review-surface.styles'
import { useChangeSetReview } from './use-change-set-review'

/**
 * The props the Change Control review lane hands every surface. Typed here rather than
 * imported: the review contract is not published to consuming bundles yet.
 */
export interface ImportConfigReviewSurfaceProps {
  readonly subjectRef: string
  readonly changeSetId?: string
  readonly contextRef?: string
  readonly onExcludedChange?: (paths: string[]) => void
  readonly onStatsChange?: (changed: number) => void
}

/** above this many changed leaves the groups start folded, or the rail is a wall of text */
const COLLAPSE_ABOVE = 12

/**
 * An import configuration reviews as the importer's own editor: the proposed configuration
 * mounted read-only, with every changed field marked where it sits. The rail summarises the
 * change the way the editor is laid out, and its addresses are the ones the merge accepts,
 * so unticking one withholds it.
 */
export const ImportConfigReviewSurface: React.FC<ImportConfigReviewSurfaceProps> = ({
  subjectRef, changeSetId, contextRef, onExcludedChange, onStatsChange
}) => {
  const { styles } = useStyles()
  const { data, isLoading, error } = useChangeSetReview(changeSetId, contextRef)
  const payload = data as ReviewPayload | undefined

  // the review payload carries only what changed; the editor needs the whole document
  const { data: liveConfig, isLoading: liveLoading } = useBundleDataImporterConfigGetQuery({ name: subjectRef })
  const live = liveConfig?.configuration as BackendConfiguration | undefined

  const changes = useMemo(() => configChanges(payload), [payload])
  const groups = useMemo(() => groupChanges(changes), [changes])
  const mappings = useMemo(() => mappingDiff(payload), [payload])
  const configuration = useMemo(() => proposedConfiguration(payload, live), [payload, live])
  const isNew = useMemo(() => isNewConfiguration(payload), [payload])

  const [excluded, setExcluded] = useState<ReadonlySet<string>>(new Set())
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set())
  const [seeded, setSeeded] = useState(false)

  // a big change set opens folded; a small one has nothing to hide
  useEffect(() => {
    if (seeded || groups.length === 0) return
    setSeeded(true)
    if (changes.length > COLLAPSE_ABOVE) {
      setCollapsed(new Set(groups.map((group) => group.section)))
    }
  }, [seeded, groups, changes.length])

  const included = useMemo(
    () => changes.filter((change) => !excluded.has(change.address)),
    [changes, excluded]
  )
  const changedMappings = useMemo(
    () => mappings.filter((row) => row.status !== 'unchanged'),
    [mappings]
  )

  useEffect(() => {
    onStatsChange?.(included.length + changedMappings.length)
  }, [included.length, changedMappings.length, onStatsChange])

  useEffect(() => {
    onExcludedChange?.([...excluded])
  }, [excluded, onExcludedChange])

  const annotations = useMemo(() => annotationsFor(included), [included])

  const toggleExcluded = (addresses: string[], include: boolean): void => {
    setExcluded((previous) => {
      const next = new Set(previous)
      addresses.forEach((address) => {
        if (include) next.delete(address)
        else next.add(address)
      })
      return next
    })
  }

  const toggleCollapsed = (section: string): void => {
    setCollapsed((previous) => {
      const next = new Set(previous)
      if (next.has(section)) next.delete(section)
      else next.add(section)
      return next
    })
  }

  if (error != null) {
    return <div className={ styles.state }>The proposed changes could not be loaded.</div>
  }
  if (isLoading || liveLoading || payload === undefined) {
    return <div className={ styles.state }>Loading the proposed configuration…</div>
  }

  const editor = (
    <DataImporterConfigEditor
      configName={ subjectRef }
      configuration={ configuration }
      isWriteable={ false }
      onSave={ async () => ({}) }
      showRuntime={ false }
    />
  )

  return (
    <div className={ styles.layout }>
      <aside className={ styles.rail }>
        { isNew
          ? (
            <NewConfigurationSummary
              mappingCount={ mappings.length }
              name={ subjectRef }
              settingCount={ changes.length }
              styles={ styles }
            />
            )
          : (
            <>
              <div className={ styles.railHead }>
                <span>Proposed changes</span>
                <span className={ styles.count }>{ included.length + changedMappings.length }</span>
              </div>

              { groups.length === 0 && changedMappings.length === 0 && (
                <div className={ styles.state }>Nothing is changed.</div>
              ) }

              { groups.map((group) => (
                <Section
                  collapsed={ collapsed.has(group.section) }
                  excluded={ excluded }
                  group={ group }
                  key={ group.section }
                  onToggleCollapsed={ () => { toggleCollapsed(group.section) } }
                  onToggleExcluded={ toggleExcluded }
                  styles={ styles }
                />
              )) }

              { changedMappings.length > 0 && (
                <MappingSection
                  rows={ changedMappings }
                  styles={ styles }
                />
              ) }
            </>
            ) }
      </aside>

      <div className={ styles.editor }>
        { FormAnnotationsProvider !== null
          ? <FormAnnotationsProvider annotations={ annotations }>{ editor }</FormAnnotationsProvider>
          : editor }
      </div>
    </div>
  )
}
