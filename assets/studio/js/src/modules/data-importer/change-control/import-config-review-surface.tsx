/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { DataImporterConfigEditor } from '../components/data-importer-config-editor'
import { useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import type { BackendConfiguration } from '../utils/transformers'
import { FormAnnotationsProvider, type FormAnnotations } from './studio-form-annotations'
import {
  annotationsFor, configChanges, groupByTab, isNewConfiguration, proposedConfiguration,
  SECTION_TARGET, TAB_LABELS, type ConfigChange, type ReviewPayload
} from './config-review-model'
import { mappingDiff } from './mapping-diff'
import {
  ChangeTree, MappingSection, NewConfigurationSummary, RailFilters, RailHead,
  type StateFilter
} from './change-rail'
import { useStyles } from './import-config-review-surface.styles'
import { useChangeSetReview } from './use-change-set-review'
import { fieldAnchorId, useJumpToField } from './use-jump-to-field'

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

/** above this many changed leaves the tabs start folded, or the rail is a wall of text */
const COLLAPSE_ABOVE = 12

const MAPPING_TARGET = SECTION_TARGET.mapping

/**
 * An import configuration reviews as the importer's own editor: the proposed configuration
 * mounted read-only, with every changed field marked where it sits.
 *
 * The rail is a map of the change, not a list of it — a row names a place in the editor and
 * carries the reader there, which is why this owns the tab and the step rather than letting
 * the tab strip keep them to itself.
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
  const mappings = useMemo(() => mappingDiff(payload), [payload])
  const configuration = useMemo(() => proposedConfiguration(payload, live), [payload, live])
  const isNew = useMemo(() => isNewConfiguration(payload), [payload])

  const [excluded, setExcluded] = useState<ReadonlySet<string>>(new Set())
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set())
  const [filter, setFilter] = useState<StateFilter>('All')
  const [query, setQuery] = useState('')
  const [tab, setTab] = useState('general')
  const [step, setStep] = useState<number | undefined>(undefined)
  const seeded = useRef(false)

  const paneRef = useRef<HTMLDivElement>(null)
  const { target, jumpTo } = useJumpToField(paneRef)

  const visible = useMemo(() => changes.filter((change) => {
    if (filter !== 'All' && change.status !== filter.toLowerCase()) return false
    const needle = query.trim().toLowerCase()
    return needle === '' || change.label.toLowerCase().includes(needle)
  }), [changes, filter, query])

  const tabGroups = useMemo(() => groupByTab(visible), [visible])

  // a big change set opens folded; a small one has nothing to hide
  useEffect(() => {
    if (seeded.current || tabGroups.length === 0) return
    seeded.current = true
    if (changes.length > COLLAPSE_ABOVE) {
      setCollapsed(new Set(tabGroups.map((group) => group.tab)))
    }
  }, [tabGroups, changes.length])

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

  // an anchor rides in each annotation's hint, which is a node the form renders in place
  const annotations = useMemo<FormAnnotations>(() => {
    const base = annotationsFor(included)
    const marked: FormAnnotations = {}
    for (const [path, annotation] of Object.entries(base)) {
      marked[path] = {
        ...annotation,
        hint: (
          <>
            <span
              data-field-anchor={ fieldAnchorId(path) }
              id={ fieldAnchorId(path) }
            />
            { annotation.hint }
          </>
        )
      }
    }
    return marked
  }, [included])

  const toggleExcluded = useCallback((addresses: string[], include: boolean): void => {
    setExcluded((previous) => {
      const next = new Set(previous)
      addresses.forEach((address) => {
        if (include) next.delete(address)
        else next.add(address)
      })
      return next
    })
  }, [])

  const toggleCollapsed = useCallback((key: string): void => {
    setCollapsed((previous) => {
      const next = new Set(previous)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }, [])

  const jumpToChange = useCallback((change: ConfigChange): void => {
    const destination = SECTION_TARGET[change.section]
    if (destination === undefined) return
    setTab(destination.tab)
    if (destination.step !== undefined) setStep(destination.step)
    if (change.formPath !== undefined) jumpTo(change.formPath, change.address)
  }, [jumpTo])

  const jumpToMappings = useCallback((): void => {
    setTab(MAPPING_TARGET.tab)
    setStep(MAPPING_TARGET.step)
  }, [])

  if (error != null) {
    return <div className={ styles.state }>The proposed changes could not be loaded.</div>
  }
  if (isLoading || liveLoading || payload === undefined) {
    return <div className={ styles.state }>Loading the proposed configuration…</div>
  }

  const counts = { changed: 0, added: 0, removed: 0 }
  changes.forEach((change) => {
    if (change.status in counts) counts[change.status as keyof typeof counts] += 1
  })

  const editor = (
    <DataImporterConfigEditor
      activeStep={ step }
      activeTab={ tab }
      configName={ subjectRef }
      configuration={ configuration }
      isWriteable={ false }
      onSave={ async () => ({}) }
      onStepChange={ setStep }
      onTabChange={ setTab }
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
              <RailHead
                added={ counts.added }
                changed={ counts.changed }
                removed={ counts.removed }
                styles={ styles }
                tabCount={ Object.keys(TAB_LABELS).length }
                tabsTouched={ groupByTab(changes).length }
              />
              <RailFilters
                filter={ filter }
                onFilter={ setFilter }
                onQuery={ setQuery }
                query={ query }
                styles={ styles }
              />
              <div className={ styles.tree }>
                <ChangeTree
                  activeTab={ tab }
                  collapsed={ collapsed }
                  excluded={ excluded }
                  onJump={ jumpToChange }
                  onToggleCollapsed={ toggleCollapsed }
                  onToggleExcluded={ toggleExcluded }
                  styles={ styles }
                  tabs={ tabGroups }
                  target={ target }
                />
                { changedMappings.length > 0 && (
                  <MappingSection
                    onJump={ jumpToMappings }
                    rows={ changedMappings }
                    styles={ styles }
                  />
                ) }
              </div>
              <div className={ styles.railFoot }>
                Approve applies only what is ticked. Grouped rows — the mapping list — are proposed
                as a whole.
              </div>
            </>
            ) }
      </aside>

      <div
        className={ styles.editor }
        ref={ paneRef }
      >
        { FormAnnotationsProvider !== null
          ? <FormAnnotationsProvider annotations={ annotations }>{ editor }</FormAnnotationsProvider>
          : editor }
      </div>
    </div>
  )
}
