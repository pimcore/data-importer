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
import { useAppDispatch, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataImporterConfigEditor } from '../components/data-importer-config-editor'
import { api, useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import type { BackendConfiguration } from '../utils/transformers'
import { FormAnnotationsProvider, type FormAnnotations } from './studio-form-annotations'
import {
  annotationsFor, configChanges, formatValue, groupByTab, isNewConfiguration, mappingAnnotations,
  proposedConfiguration, SECTION_TARGET, type ConfigChange, type ReviewMode, type ReviewPayload, type ValueLabels
} from './config-review-model'
import { mappingDiff } from './mapping-diff'
import { ChangeTree, MappingSection, NewConfigurationSummary } from './change-rail'
import { HistoryHead } from './history-head'
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
  /** review while the change set is open; history once it is resolved */
  readonly mode?: ReviewMode
  /** the resolved state — 'merged' | 'discarded' | 'refined' — shown in history */
  readonly state?: string
  readonly resolvedAt?: number
  readonly onExcludedChange?: (paths: string[]) => void
  readonly onStatsChange?: (changed: number) => void
}

const T = 'data-importer.review'

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
  subjectRef, changeSetId, contextRef, mode = 'review', state, resolvedAt, onExcludedChange, onStatsChange
}) => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { data, isLoading, error } = useChangeSetReview(changeSetId, contextRef)
  const payload = data as ReviewPayload | undefined
  const history = mode === 'history'

  // the review payload carries only what changed; the editor needs the whole document
  const { data: liveConfig, isLoading: liveLoading, isError: liveMissing } = useBundleDataImporterConfigGetQuery({ name: subjectRef })
  const live = liveConfig?.configuration as BackendConfiguration | undefined
  const dispatch = useAppDispatch()

  const changes = useMemo(() => configChanges(payload, mode), [payload, mode])
  const mappings = useMemo(() => mappingDiff(payload, mode), [payload, mode])
  const configuration = useMemo(() => proposedConfiguration(payload, live, mode), [payload, live, mode])
  const isNew = useMemo(() => isNewConfiguration(payload, mode), [payload, mode])

  // a configuration that does not exist yet has no live document, and the editor's steps read
  // one by name; the proposed document stands in, so the mapping step has something to load
  useEffect(() => {
    if (!liveMissing || payload === undefined) return
    void dispatch(api.util.upsertQueryData('bundleDataImporterConfigGet', { name: subjectRef }, {
      name: subjectRef,
      configuration: configuration as Record<string, object>,
      // nothing to write and nothing written yet: the review is read-only either way
      userPermissions: { update: false, delete: false },
      modificationDate: 0
    }))
  }, [liveMissing, payload, configuration, subjectRef, dispatch])

  const [excluded, setExcluded] = useState<ReadonlySet<string>>(new Set())
  const [tab, setTab] = useState('general')
  const [step, setStep] = useState<number | undefined>(undefined)

  const paneRef = useRef<HTMLDivElement>(null)
  const { target, jumpTo } = useJumpToField(paneRef)

  const tabGroups = useMemo(() => groupByTab(changes), [changes])

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

  const labels = useMemo<ValueLabels>(() => ({
    empty: t(`${T}.value.empty`),
    yes: t(`${T}.value.yes`),
    no: t(`${T}.value.no`)
  }), [t])

  // an anchor rides in each annotation's hint, which is a node the form renders in place
  const annotations = useMemo<FormAnnotations>(() => {
    // a path or a cron expression is printed as it is; HTML escaping is for markup, not text nodes
    const was = (value: string): string => t(`${T}.was`, { value, interpolation: { escapeValue: false } })
    const base = {
      ...annotationsFor(included, (change) => was(formatValue(change.current, labels))),
      ...mappingAnnotations(payload, mode, was)
    }
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
  }, [included, payload, mode, labels, t])

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
    return <div className={ styles.state }>{ t(`${T}.load-failed`) }</div>
  }
  if (isLoading || liveLoading || payload === undefined) {
    return <div className={ styles.state }>{ t(`${T}.loading`) }</div>
  }

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

  const mappingsActive = tab === MAPPING_TARGET.tab && step === MAPPING_TARGET.step

  return (
    <div className={ styles.layout }>
      <aside className={ styles.rail }>
        { history && state !== undefined && (
          <HistoryHead
            resolvedAt={ resolvedAt }
            state={ state }
            styles={ styles }
          />
        ) }
        <div className={ styles.list }>
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
                <div className={ styles.caption }>
                  { t(`${T}.changes`, { count: included.length + changedMappings.length }) }
                </div>
                <ChangeTree
                  activeTab={ tab }
                  excluded={ excluded }
                  onJump={ jumpToChange }
                  onToggleExcluded={ history ? undefined : toggleExcluded }
                  styles={ styles }
                  tabs={ tabGroups }
                  target={ target }
                />
                { changedMappings.length > 0 && (
                  <MappingSection
                    active={ mappingsActive }
                    onJump={ jumpToMappings }
                    rows={ changedMappings }
                    styles={ styles }
                  />
                ) }
              </>
              ) }
        </div>
        { !history && !isNew && <div className={ styles.foot }>{ t(`${T}.foot`) }</div> }
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
