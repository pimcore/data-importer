/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Flex, Skeleton } from '@pimcore/studio-ui-bundle/components'
import { ColumnPanel, type ColumnPanelTab } from './column-panel'
import {
  autoRowCount,
  chooseField,
  pendingSuggestionCount,
  requiredFields,
  restoreColumn,
  rowCounts,
  setTransformations,
  skipColumn,
  skipOpenColumns,
  skippedRows,
  takeAllSuggestions,
  unfitRows,
  visibleRows,
  type Mapping,
  type MappingField,
  type MappingRow,
  type RowFilter
} from './mapping-state'
import { chooseStoreKey, withLanguages } from './mapping-choices'
import { MappingRowView } from './mapping-row'
import { StoreKeyDialog } from './store-key-dialog'
import { takenStoreKeys } from './taken-store-keys'
import { useLanguages } from './use-languages'
import { ConfigurationChoice, type ConfigurationChoiceProps } from './configuration-choice'
import { type MapWith } from './configuration-state'
import { MapWithSwitch } from './map-with-switch'
import { MappingSummary } from './mapping-summary'
import { NotImportedPanel } from './not-imported-panel'
import { useStyles } from './mapping-step.styles'
import { type TransformationSource } from './transformations-editor'

const MAPPING = 'data-importer.ad-hoc-import.mapping'

export interface MappingStepProps {
  // the class the objects are of, whose classification store keys are offered
  classId: string
  mapping: Mapping | null
  fields: MappingField[]
  isLoading: boolean
  onChange: (mapping: Mapping) => void
  mapWith: MapWith
  onMapWithChange: (mapWith: MapWith) => void
  configuration: ConfigurationChoiceProps
  // what the transformations of a column run on; none, no transformations
  source: TransformationSource | null
}

export const MappingStep = ({
  classId,
  mapping,
  fields,
  isLoading,
  onChange,
  mapWith,
  onMapWithChange,
  configuration,
  source
}: MappingStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const languages = useLanguages()
  const [filter, setFilter] = useState<RowFilter>('all')
  // the column the side panel is open on, and on which tab
  const [panel, setPanel] = useState<{ column: string, tab: ColumnPanelTab } | null>(null)
  // the column and store whose key is being chosen
  const [storeKeyFor, setStoreKeyFor] = useState<{ column: string, field: string } | null>(null)

  // a column going into a localized field always holds a language
  const settle = (next: Mapping): Mapping => withLanguages(next, fields, languages)
  const change = (next: Mapping): void => { onChange(settle(next)) }

  const required = useMemo(() => mapping === null ? [] : requiredFields(mapping, fields), [mapping, fields])
  const unfit = useMemo(() => mapping === null ? [] : unfitRows(mapping, fields), [mapping, fields])

  const mapWithSwitch = (
    <MapWithSwitch
      onChange={ onMapWithChange }
      value={ mapWith }
    />
  )

  // the mapping made here stays for a switch back
  if (mapWith === 'configuration') {
    return (
      <Flex
        gap="normal"
        vertical
      >
        { mapWithSwitch }
        <ConfigurationChoice { ...configuration } />
      </Flex>
    )
  }

  if (mapping === null) {
    return (
      <Flex
        gap="normal"
        vertical
      >
        { mapWithSwitch }
        { isLoading && <Skeleton active /> }
      </Flex>
    )
  }

  const pick = (row: MappingRow, field: MappingField): void => {
    if (field.classificationStore) {
      setStoreKeyFor({ column: row.column, field: field.key })
      return
    }
    change(chooseField(mapping, row.column, field.key))
  }

  const counts = rowCounts(mapping)
  const rows = visibleRows(mapping, filter)
  const skipped = skippedRows(mapping)
  const panelRow = mapping.rows.find(({ column }) => column === panel?.column)

  // the columns the panel can move over: those that can take a field
  const panelRows = visibleRows(mapping, 'all').filter(({ isMatch }) => !isMatch)
  const moveBy = (step: number): void => {
    if (panelRow === undefined || panelRows.length === 0) {
      return
    }
    const position = panelRows.findIndex(({ column }) => column === panelRow.column)
    const next = panelRows[(position + step + panelRows.length) % panelRows.length]
    setPanel({ column: next.column, tab: panel!.tab })
  }

  const openPanel = (column: string, tab: ColumnPanelTab): void => { setPanel({ column, tab }) }
  const skip = (column: string): void => {
    if (panel?.column === column) {
      setPanel(null)
    }
    change(skipColumn(mapping, column))
  }

  return (
    <Flex
      gap="normal"
      vertical
    >
      { mapWithSwitch }

      <MappingSummary
        auto={ autoRowCount(mapping) }
        counts={ counts }
        filter={ filter }
        onFilter={ setFilter }
        onSkipOpen={ () => {
          if (panelRow !== undefined && panelRow.choice === undefined && !panelRow.isMatch && !panelRow.skipped) {
            setPanel(null)
          }
          change(skipOpenColumns(mapping))
        } }
        onUseSuggestions={ () => { change(takeAllSuggestions(mapping)) } }
        pending={ pendingSuggestionCount(mapping, settle) }
        required={ required }
        skipped={ skipped.length }
        total={ mapping.rows.length }
      />

      { unfit.length > 0 && (
        <Alert
          message={ t(`${MAPPING}.not-fitting`, {
            columns: unfit.map(({ label }) => label).join(', '),
            interpolation: { escapeValue: false }
          }) }
          showIcon
          type="warning"
        />
      ) }

      <div className={ styles.table }>
        <div
          aria-hidden
          className={ styles.head }
        >
          <span />
          <span className={ styles.headCell }>{ t(`${MAPPING}.head.column`) }</span>
          <span />
          <span className={ styles.headCell }>{ t(`${MAPPING}.head.field`) }</span>
          <span className={ styles.headCell }>{ t(`${MAPPING}.head.transformations`) }</span>
          <span />
        </div>
        <div
          aria-label={ t(`${MAPPING}.columns`) }
          role="list"
        >
          { rows.map((row) => (
            <MappingRowView
              fields={ fields }
              isActive={ row.column === panel?.column }
              key={ row.column }
              languages={ languages }
              mapping={ mapping }
              onChange={ change }
              onOpenField={ (column) => { openPanel(column, 'field') } }
              onOpenTransformations={ source === null ? undefined : (column) => { openPanel(column, 'transformations') } }
              onPickStoreKey={ (column, field) => { setStoreKeyFor({ column, field }) } }
              onSkip={ skip }
              row={ row }
            />
          )) }
        </div>
        { skipped.length > 0 && (
          <NotImportedPanel
            onRestore={ (column) => { change(restoreColumn(mapping, column)) } }
            skipped={ skipped }
          />
        ) }
      </div>

      { storeKeyFor !== null && (
        <StoreKeyDialog
          classId={ classId }
          fieldName={ storeKeyFor.field }
          onClose={ () => { setStoreKeyFor(null) } }
          onSelect={ (key) => {
            change(chooseStoreKey(mapping, storeKeyFor.column, storeKeyFor.field, key, languages))
            setStoreKeyFor(null)
          } }
          takenKeyIds={ takenStoreKeys(mapping.rows, { key: storeKeyFor.field, localized: fields.some(({ key, localized }) => key === storeKeyFor.field && localized) }, storeKeyFor.column, languages) }
        />
      ) }

      { panel !== null && panelRow !== undefined && (
        <ColumnPanel
          fields={ fields }
          key={ panelRow.column }
          languages={ languages }
          mapping={ mapping }
          onClose={ () => { setPanel(null) } }
          onFits={ (steps, fits) => { onChange(setTransformations(mapping, panelRow.column, steps, fits)) } }
          onNext={ () => { moveBy(1) } }
          onPick={ pick }
          onPrevious={ () => { moveBy(-1) } }
          onRemoveField={ () => { change(chooseField(mapping, panelRow.column, undefined)) } }
          onSkip={ () => { skip(panelRow.column) } }
          onSteps={ (steps) => { onChange(setTransformations(mapping, panelRow.column, steps, undefined)) } }
          onTabChange={ (tab) => { setPanel({ column: panelRow.column, tab }) } }
          row={ panelRow }
          source={ source }
          tab={ panel.tab }
        />
      ) }
    </Flex>
  )
}
