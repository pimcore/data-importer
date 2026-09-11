/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { useAppDispatch, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Content } from '@pimcore/studio-ui-bundle/components'
import { DataImporterConfigEditor } from '../components/data-importer-config-editor'
import { api, useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import type { BackendConfiguration } from '../utils/transformers'
import { FormAnnotationsProvider, type FormAnnotations } from './studio-form-annotations'
import {
  annotationsFor, configChanges, groupChanges, isNewConfiguration, mappingAnnotations,
  proposedConfiguration, SECTION_TARGET, type ConfigChange, type ReviewMode, type ReviewPayload
} from './config-review-model'
import { fieldLabel } from './field-labels'
import { mappingDiff } from './mapping-diff'
import { ChangeList } from './change-rail'
import { HistoryHead } from './history-head'
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
  /** review while the change set is open; history once it is resolved */
  readonly mode?: ReviewMode
  /** the resolved state — 'merged' | 'discarded' | 'refined' — shown in history */
  readonly state?: string
  readonly resolvedAt?: number
  readonly onExcludedChange?: (paths: string[]) => void
  readonly onStatsChange?: (changed: number) => void
}

const T = 'data-importer.review'

/**
 * An import configuration reviews as the importer's own editor: the proposed configuration
 * mounted read-only, with every changed field marked where it sits.
 *
 * The rail is a map of the change, not a list of it — a row names a place in the editor and
 * carries the reader there, which is why this owns the tab and the step rather than letting
 * the tab strip keep them to itself. A configuration is approved or rejected whole: the rail
 * decides nothing.
 */
export const ImportConfigReviewSurface: React.FC<ImportConfigReviewSurfaceProps> = ({
  subjectRef, changeSetId, contextRef, mode = 'review', state, resolvedAt, onStatsChange
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
  const groups = useMemo(() => groupChanges(changes), [changes])
  const changedMappings = useMemo(() => mappings.filter((row) => row.status !== 'unchanged'), [mappings])

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

  const [tab, setTab] = useState('general')
  const [step, setStep] = useState<number | undefined>(undefined)

  // a count is a verdict; none until the change set has actually been read
  useEffect(() => {
    if (payload === undefined) return
    onStatsChange?.(changes.length + changedMappings.length)
  }, [payload, changes.length, changedMappings.length, onStatsChange])

  const annotations = useMemo<FormAnnotations>(
    () => ({ ...annotationsFor(changes), ...mappingAnnotations(payload, mode) }),
    [changes, payload, mode]
  )

  const jumpToSection = useCallback((section: string): void => {
    const destination = SECTION_TARGET[section]
    if (destination === undefined) return
    setTab(destination.tab)
    setStep(destination.step)
  }, [])

  const labelFor = useCallback(
    (change: ConfigChange): string => fieldLabel(change.address, configuration, t),
    [configuration, t]
  )

  // the section the editor is showing: the one whose tab and step are the current ones
  const activeSection = useMemo(() => Object.entries(SECTION_TARGET)
    .find(([, place]) => place.tab === tab && (place.step === undefined || place.step === step))?.[0],
  [tab, step])

  if (error != null) {
    return <div className={ styles.state }>{ t(`${T}.load-failed`) }</div>
  }
  if (isLoading || liveLoading || payload === undefined) {
    return <Content loading />
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
          <div className={ styles.summary }>
            { isNew && <>{ t(`${T}.new.title`) } · </> }
            { t(`${T}.changes`, { count: changes.length + changedMappings.length }) }
          </div>
          <ChangeList
            activeSection={ activeSection }
            groups={ groups }
            labelFor={ labelFor }
            mappings={ changedMappings }
            onJump={ jumpToSection }
            styles={ styles }
          />
        </div>
      </aside>

      <div className={ styles.editor }>
        { FormAnnotationsProvider !== null
          ? <FormAnnotationsProvider annotations={ annotations }>{ editor }</FormAnnotationsProvider>
          : editor }
      </div>
    </div>
  )
}
