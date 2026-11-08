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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Flex, SearchInput, Segmented, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import { FieldPanel } from './field-panel'
import {
  chooseField,
  missingRequiredFields,
  pendingSuggestionCount,
  restoreColumn,
  rowCounts,
  setTransformations,
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
import { takenFields } from './taken-fields'
import { MappingRowView } from './mapping-row'
import { StoreKeyDialog } from './store-key-dialog'
import { takenStoreKeys } from './taken-store-keys'
import { useLanguages } from './use-languages'
import { ConfigurationChoice, type ConfigurationChoiceProps } from './configuration-choice'
import { type MapWith } from './configuration-state'
import { MapWithSwitch } from './map-with-switch'
import { NotImportedPanel } from './not-imported-panel'
import { TransformationsModal, type TransformationSource } from './transformations-modal'

const SEARCH_WIDTH = 240
const PANEL_WIDTH = 320
const FILTERS: RowFilter[] = ['all', 'mapped', 'open']

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `,
  rows: css`
    flex: 1;
    min-width: 0;
  `,
  panel: css`
    width: ${PANEL_WIDTH}px;
    flex: none;
  `
}))

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
  const [search, setSearch] = useState('')
  const [activeColumn, setActiveColumn] = useState<string | null>(null)
  // the column and store whose key is being chosen
  const [storeKeyFor, setStoreKeyFor] = useState<{ column: string, field: string } | null>(null)

  // a column going into a localized field always holds a language
  const settle = (next: Mapping): Mapping => withLanguages(next, fields, languages)
  const change = (next: Mapping): void => { onChange(settle(next)) }
  // the column whose transformations are open
  const [editing, setEditing] = useState<string | null>(null)

  const missing = useMemo(() => mapping === null ? [] : missingRequiredFields(mapping, fields), [mapping, fields])
  const unfit = useMemo(() => mapping === null ? [] : unfitRows(mapping, fields), [mapping, fields])
  const taken = useMemo(() => takenFields(mapping?.rows ?? []), [mapping?.rows])

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

  // the column picked last, else the first one that can take a field
  const activeRow = mapping.rows.find((row) => row.column === activeColumn && !row.isMatch && !row.skipped) ??
    mapping.rows.find((row) => !row.isMatch && !row.skipped)

  const pick = (row: MappingRow, field: MappingField): void => {
    if (field.classificationStore) {
      setStoreKeyFor({ column: row.column, field: field.key })
      return
    }
    change(chooseField(mapping, row.column, field.key))
  }

  const editedRow = mapping.rows.find(({ column }) => column === editing)
  const editedField = fields.find(({ key }) => key === editedRow?.choice?.field)

  const counts = rowCounts(mapping)
  const pending = pendingSuggestionCount(mapping, settle)
  const rows = visibleRows(mapping, filter, search)
  const skipped = skippedRows(mapping)

  return (
    <Flex
      gap="normal"
      vertical
    >
      { mapWithSwitch }

      <Flex
        align="center"
        gap="small"
        justify="space-between"
      >
        <Flex
          align="center"
          gap="small"
        >
          <Segmented
            onChange={ (next) => { setFilter(next as RowFilter) } }
            options={ FILTERS.map((value) => ({
              value,
              label: t(`data-importer.ad-hoc-import.mapping.filter.${value}`, { count: counts[value] })
            })) }
            value={ filter }
          />
          <SearchInput
            aria-label={ t('data-importer.ad-hoc-import.mapping.search') }
            maxWidth={ SEARCH_WIDTH }
            onChange={ (event) => { setSearch(event.target.value) } }
            placeholder={ t('data-importer.ad-hoc-import.mapping.search') }
            value={ search }
            withClear
            withPrefix
            withoutAddon
          />
        </Flex>
        <Button
          disabled={ pending === 0 }
          onClick={ () => { change(takeAllSuggestions(mapping)) } }
        >
          { t('data-importer.ad-hoc-import.mapping.use-all-suggestions', { count: pending }) }
        </Button>
      </Flex>

      { missing.length > 0 && (
        <Alert
          message={ t('data-importer.ad-hoc-import.mapping.missing-required', {
            fields: missing.map(({ title }) => title).join(', '),
            interpolation: { escapeValue: false }
          }) }
          showIcon
          type="warning"
        />
      ) }

      { unfit.length > 0 && (
        <Alert
          message={ t('data-importer.ad-hoc-import.mapping.not-fitting', {
            columns: unfit.map(({ label }) => label).join(', '),
            interpolation: { escapeValue: false }
          }) }
          showIcon
          type="warning"
        />
      ) }

      <Flex
        align="flex-start"
        gap="normal"
      >
        <Flex
          aria-label={ t('data-importer.ad-hoc-import.mapping.columns') }
          className={ styles.rows }
          gap="small"
          role="list"
          vertical
        >
          { rows.map((row) => (
            <MappingRowView
              fields={ fields }
              isActive={ row.column === activeRow?.column }
              key={ row.column }
              languages={ languages }
              mapping={ mapping }
              onActivate={ setActiveColumn }
              onChange={ change }
              onPickStoreKey={ (column, field) => { setStoreKeyFor({ column, field }) } }
              onTransform={ source === null ? undefined : setEditing }
              row={ row }
              taken={ taken }
            />
          )) }
          { rows.length === 0 && <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.mapping.no-rows') }</Text> }
        </Flex>
        <div className={ styles.panel }>
          <FieldPanel
            fields={ fields }
            languages={ languages }
            mapping={ mapping }
            onPick={ pick }
            row={ activeRow }
          />
        </div>
      </Flex>

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

      { skipped.length > 0 && (
        <NotImportedPanel
          onRestore={ (column) => { change(restoreColumn(mapping, column)) } }
          skipped={ skipped }
        />
      ) }

      { source !== null && editedRow !== undefined && editedField !== undefined && (
        <TransformationsModal
          field={ editedField }
          onApply={ (transformations, fits) => {
            onChange(setTransformations(mapping, editedRow.column, transformations, fits))
            setEditing(null)
          } }
          onCancel={ () => { setEditing(null) } }
          row={ editedRow }
          source={ source }
        />
      ) }
    </Flex>
  )
}
