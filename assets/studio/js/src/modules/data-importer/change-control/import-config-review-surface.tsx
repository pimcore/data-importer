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
import { Checkbox, Tag } from '@pimcore/studio-ui-bundle/components'
import { DataImporterConfigEditor } from '../components/data-importer-config-editor'
import { useBundleDataImporterConfigGetQuery } from '../data-importer-api-slice-enhanced'
import type { BackendConfiguration } from '../utils/transformers'
import { FormAnnotationsProvider } from './studio-form-annotations'
import {
  annotationsFor, configChanges, formatValue, mappingDiff, proposedConfiguration,
  type ConfigChange, type ReviewPayload
} from './config-review-model'
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

const STATUS_COLOR: Record<string, string> = {
  added: 'success',
  changed: 'warning',
  removed: 'error',
  unchanged: 'default',
  moved: 'processing'
}

/**
 * An import configuration reviews as the importer's own editor: the proposed configuration
 * mounted read-only, with every changed field marked where it sits. The change list on the
 * left is the same set of addresses the merge accepts, so unticking one withholds it.
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

  const [excluded, setExcluded] = useState<ReadonlySet<string>>(new Set())

  const included = useMemo(
    () => changes.filter((change) => !excluded.has(change.address)),
    [changes, excluded]
  )

  useEffect(() => {
    onStatsChange?.(included.length + mappings.filter((row) => row.status !== 'unchanged').length)
  }, [included.length, mappings, onStatsChange])

  useEffect(() => {
    onExcludedChange?.([...excluded])
  }, [excluded, onExcludedChange])

  const annotations = useMemo(() => annotationsFor(included), [included])

  const toggle = (address: string): void => {
    setExcluded((previous) => {
      const next = new Set(previous)
      if (next.has(address)) next.delete(address)
      else next.add(address)
      return next
    })
  }

  if (error != null) {
    return <div className={ styles.state }>The proposed changes could not be loaded.</div>
  }
  if (isLoading || liveLoading || payload === undefined) {
    return <div className={ styles.state }>Loading the proposed configuration…</div>
  }

  const changedMappings = mappings.filter((row) => row.status !== 'unchanged')

  const editor = (
    <DataImporterConfigEditor
      configName={ subjectRef }
      configuration={ configuration }
      isWriteable={ false }
      onSave={ async () => ({}) }
    />
  )

  return (
    <div className={ styles.layout }>
      <aside className={ styles.rail }>
        <div className={ styles.railTitle }>Proposed changes</div>

        { changes.length === 0 && changedMappings.length === 0 && (
          <div className={ styles.state }>Nothing is changed.</div>
        ) }

        { changes.map((change) => (
          <div
            className={ styles.change }
            key={ change.address }
          >
            <Checkbox
              checked={ !excluded.has(change.address) }
              onChange={ () => { toggle(change.address) } }
            />
            <span className={ styles.changeBody }>
              <span className={ styles.changeHead }>
                <span className={ styles.changeLabel }>{ labelFor(change) }</span>
                <Tag color={ STATUS_COLOR[change.status] }>{ change.status }</Tag>
              </span>
              <span className={ styles.changeValues }>
                <span className={ styles.was }>{ formatValue(change.current) }</span>
                <span>→</span>
                <span className={ styles.now }>{ formatValue(change.proposed) }</span>
              </span>
              <span className={ styles.address }>{ change.address }</span>
            </span>
          </div>
        )) }

        { changedMappings.length > 0 && (
          <>
            <div className={ styles.railTitle }>Mappings</div>
            { changedMappings.map((row) => (
              <div
                className={ styles.mapping }
                key={ row.key }
              >
                <span className={ styles.changeHead }>
                  <span className={ styles.changeLabel }>{ row.label }</span>
                  <Tag color={ STATUS_COLOR[row.status] }>{ row.status }</Tag>
                </span>
                <span className={ styles.changeValues }>
                  { row.currentTarget !== undefined && row.currentTarget !== row.target && (
                    <><span className={ styles.was }>{ row.currentTarget }</span><span>→</span></>
                  ) }
                  <span className={ styles.now }>{ row.target }</span>
                </span>
              </div>
            )) }
            <div className={ styles.note }>
              The mapping list is proposed as a whole — individual rows cannot be withheld.
            </div>
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

function labelFor (change: ConfigChange): string {
  const segments = change.address.split('.')
  return segments[segments.length - 1]
}
