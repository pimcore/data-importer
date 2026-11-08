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
import { Alert, Button, Flex, FormKit, SearchInput, Segmented, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import {
  missingRequiredFields,
  pendingSuggestionCount,
  restoreColumn,
  rowCounts,
  setTransformations,
  skippedRows,
  takeAllSuggestions,
  takenFields,
  unfitRows,
  visibleRows,
  type Mapping,
  type MappingField,
  type RowFilter
} from './mapping-state'
import { MappingRowView } from './mapping-row'
import { ConfigurationChoice, type ConfigurationChoiceProps } from './configuration-choice'
import { type MapWith } from './configuration-state'
import { TransformationsModal, type TransformationSource } from './transformations-modal'

const SEARCH_WIDTH = 240
const FILTERS: RowFilter[] = ['all', 'mapped', 'open']

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `
}))

export interface MappingStepProps {
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
  const [filter, setFilter] = useState<RowFilter>('all')
  const [search, setSearch] = useState('')
  // the column whose transformations are open
  const [editing, setEditing] = useState<string | null>(null)

  const missing = useMemo(() => mapping === null ? [] : missingRequiredFields(mapping, fields), [mapping, fields])
  const unfit = useMemo(() => mapping === null ? [] : unfitRows(mapping, fields), [mapping, fields])
  const taken = useMemo(() => takenFields(mapping?.rows ?? []), [mapping?.rows])

  const mapWithSwitch = (
    <Flex
      align="center"
      gap="small"
    >
      <Text>{ t('data-importer.ad-hoc-import.mapping.map-with') }</Text>
      <Segmented
        onChange={ (next) => { onMapWithChange(next as MapWith) } }
        options={ [
          { value: 'here', label: t('data-importer.ad-hoc-import.mapping.map-here') },
          { value: 'configuration', label: t('data-importer.ad-hoc-import.mapping.use-configuration') }
        ] }
        value={ mapWith }
      />
    </Flex>
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

  const editedRow = mapping.rows.find(({ column }) => column === editing)
  const editedField = fields.find(({ key }) => key === editedRow?.choice?.field)

  const counts = rowCounts(mapping)
  const pending = pendingSuggestionCount(mapping)
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
          onClick={ () => { onChange(takeAllSuggestions(mapping)) } }
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
        aria-label={ t('data-importer.ad-hoc-import.mapping.columns') }
        gap="small"
        role="list"
        vertical
      >
        { rows.map((row) => (
          <MappingRowView
            fields={ fields }
            key={ row.column }
            mapping={ mapping }
            onChange={ onChange }
            onTransform={ source === null ? undefined : setEditing }
            row={ row }
            taken={ taken }
          />
        )) }
        { rows.length === 0 && <Text className={ styles.quiet }>{ t('data-importer.ad-hoc-import.mapping.no-rows') }</Text> }
      </Flex>

      { skipped.length > 0 && (
        <FormKit.Panel title={ t('data-importer.ad-hoc-import.mapping.not-imported', { count: skipped.length }) }>
          { skipped.map(({ column, label }) => (
            <Flex
              align="center"
              gap="small"
              key={ column }
            >
              <Text>{ label }</Text>
              <Button
                aria-label={ t('data-importer.ad-hoc-import.mapping.restore-column', {
                  column: label,
                  interpolation: { escapeValue: false }
                }) }
                onClick={ () => { onChange(restoreColumn(mapping, column)) } }
                type="link"
              >
                { t('data-importer.ad-hoc-import.mapping.restore') }
              </Button>
            </Flex>
          )) }
        </FormKit.Panel>
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
