/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Flex, Select, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { filterByLabel } from '../../utils/select-utils'
import { chooseField, fitsField, skipColumn, type Mapping, type MappingField, type MappingRow, type TakenFields } from './mapping-state'

const COLUMN_WIDTH = 280
const FIELD_WIDTH = 320

// a value no field key can have: keys are PHP property names
const DONT_IMPORT = '-'

const useStyles = createStyles(({ token, css }) => ({
  column: css`
    width: ${COLUMN_WIDTH}px;
    min-width: 0;
  `,
  quiet: css`
    color: ${token.colorTextTertiary};
  `
}))

export interface MappingRowViewProps {
  row: MappingRow
  mapping: Mapping
  fields: MappingField[]
  taken: TakenFields
  onChange: (mapping: Mapping) => void
  // opens the column's transformations; none without a file to run them on
  onTransform?: (column: string) => void
}

export const MappingRowView = ({ row, mapping, fields, taken, onChange, onTransform }: MappingRowViewProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { column, label, sample, choice, auto, suggestion, isMatch, transformations } = row

  const options = useMemo(() => [
    { value: DONT_IMPORT, label: t('data-importer.ad-hoc-import.mapping.dont-import') },
    ...fields.map(({ key, title, mandatory }) => ({
      value: key,
      label: mandatory
        ? t('data-importer.ad-hoc-import.mapping.required-field', { field: title, interpolation: { escapeValue: false } })
        : title,
      // a localized field takes a column per language
      disabled: taken.has({ field: key, language: choice?.field === key ? choice.language : null }, column)
    }))
  ], [fields, taken, choice, column, t])

  const titleOf = (key: string): string => fields.find((field) => field.key === key)?.title ?? key

  const onSelect = (value?: string): void => {
    if (value === DONT_IMPORT) {
      onChange(skipColumn(mapping, column))
      return
    }
    onChange(chooseField(mapping, column, value === '' ? undefined : value))
  }

  return (
    <Flex
      align="center"
      gap="small"
      role="listitem"
    >
      <Flex
        className={ styles.column }
        vertical
      >
        <Text
          ellipsis
          strong
        >
          { label }
        </Text>
        { sample !== undefined && (
          <Text
            className={ styles.quiet }
            ellipsis
          >
            { sample }
          </Text>
        ) }
      </Flex>

      { isMatch
        ? (
          <Select
            aria-label={ t('data-importer.ad-hoc-import.mapping.field-of', { column: label, interpolation: { escapeValue: false } }) }
            disabled
            options={ [{ value: choice?.field ?? '', label: choice === undefined ? t('data-importer.ad-hoc-import.mapping.object-id') : titleOf(choice.field) }] }
            style={ { width: FIELD_WIDTH } }
            value={ choice?.field ?? '' }
          />
          )
        : (
          <Select
            allowClear
            aria-label={ t('data-importer.ad-hoc-import.mapping.field-of', { column: label, interpolation: { escapeValue: false } }) }
            filterOption={ filterByLabel }
            onChange={ onSelect }
            options={ options }
            placeholder={ suggestion === undefined
              ? t('data-importer.ad-hoc-import.mapping.choose-field')
              : t('data-importer.ad-hoc-import.mapping.suggested', { field: titleOf(suggestion.field), interpolation: { escapeValue: false } }) }
            showSearch
            style={ { width: FIELD_WIDTH } }
            value={ choice?.field }
          />
          ) }

      { !isMatch && choice !== undefined && onTransform !== undefined && (
        <Button
          aria-label={ t('data-importer.ad-hoc-import.mapping.transformations-of', { column: label, interpolation: { escapeValue: false } }) }
          onClick={ () => { onTransform(column) } }
        >
          { transformations.length === 0
            ? t('data-importer.ad-hoc-import.mapping.transformations')
            : t('data-importer.ad-hoc-import.mapping.transformations-count', { count: transformations.length }) }
        </Button>
      ) }
      { !fitsField(row, fields) && <Tag color="warning">{ t('data-importer.ad-hoc-import.mapping.needs-transformation') }</Tag> }
      { isMatch && <Tag>{ t('data-importer.ad-hoc-import.mapping.match') }</Tag> }
      { choice?.language !== null && choice?.language !== undefined && <Tag>{ choice.language }</Tag> }
      { auto && <Tag color="purple">{ t('data-importer.ad-hoc-import.mapping.auto') }</Tag> }
    </Flex>
  )
}
