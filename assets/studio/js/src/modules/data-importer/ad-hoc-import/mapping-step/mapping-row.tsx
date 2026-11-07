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
import { Button, Flex, LanguageSelection, Select, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { filterByLabel } from '../../utils/select-utils'
import {
  chooseField,
  chooseLanguage,
  canGoInto,
  freeLanguages,
  skipColumn,
  type Mapping,
  type MappingField,
  type MappingRow
} from './mapping-state'

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
  `,
  active: css`
    background: ${token.controlItemBgActive};
    border-radius: ${token.borderRadius}px;
  `
}))

export interface MappingRowViewProps {
  row: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  // the column whose field the side panel picks
  isActive: boolean
  onActivate: (column: string) => void
  onChange: (mapping: Mapping) => void
  // a classification store goes in by a key chosen in its own dialog
  onPickStoreKey: (column: string, field: string) => void
}

type ChoiceDetailsProps = Pick<MappingRowViewProps, 'row' | 'mapping' | 'languages' | 'onChange' | 'onPickStoreKey'>

// the store key and the language of the row's choice, each changeable; a language another column has is not offered
const ChoiceDetails = ({ row, mapping, languages, onChange, onPickStoreKey }: ChoiceDetailsProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { column, label, choice } = row
  if (choice === undefined) {
    return null
  }

  return (
    <>
      { choice.key !== undefined && (
        <Button
          aria-label={ t('data-importer.ad-hoc-import.mapping.change-key', { column: label, interpolation: { escapeValue: false } }) }
          onClick={ () => { onPickStoreKey(column, choice.field) } }
          type="link"
        >
          { choice.key.label }
        </Button>
      ) }
      { choice.language !== null && (
        <LanguageSelection
          languages={ freeLanguages(mapping.rows, row, languages) }
          onSelectLanguage={ (language) => { onChange(chooseLanguage(mapping, column, language)) } }
          selectedLanguage={ choice.language }
        />
      ) }
    </>
  )
}

type SelectFieldContext = Pick<MappingRowViewProps, 'mapping' | 'fields' | 'onChange' | 'onPickStoreKey'> & { column: string }

const selectField = (value: string | undefined, { mapping, fields, column, onChange, onPickStoreKey }: SelectFieldContext): void => {
  if (value === DONT_IMPORT) {
    onChange(skipColumn(mapping, column))
    return
  }
  if (fields.some(({ key, classificationStore }) => key === value && classificationStore)) {
    onPickStoreKey(column, value!)
    return
  }
  onChange(chooseField(mapping, column, value === '' ? undefined : value))
}

type FieldSelectProps = Pick<MappingRowViewProps, 'row' | 'mapping' | 'fields' | 'languages' | 'onChange' | 'onPickStoreKey'>

// the row's field: fixed for the column that matches existing objects, else chosen from the class's fields
const FieldSelect = ({ row, mapping, fields, languages, onChange, onPickStoreKey }: FieldSelectProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { column, label, choice, suggestion, isMatch } = row

  const options = useMemo(() => [
    { value: DONT_IMPORT, label: t('data-importer.ad-hoc-import.mapping.dont-import') },
    ...fields.map((field) => ({
      value: field.key,
      label: field.mandatory
        ? t('data-importer.ad-hoc-import.mapping.required-field', { field: field.title, interpolation: { escapeValue: false } })
        : field.title,
      // a localized field takes a column per language, a classification store one per key
      disabled: !canGoInto(mapping.rows, field, column, languages)
    }))
  ], [fields, mapping.rows, column, languages, t])

  const titleOf = (key: string): string => fields.find((field) => field.key === key)?.title ?? key

  const onSelect = (value?: string): void => {
    selectField(value, { mapping, fields, column, onChange, onPickStoreKey })
  }

  if (isMatch) {
    return (
      <Select
        aria-label={ t('data-importer.ad-hoc-import.mapping.field-of', { column: label, interpolation: { escapeValue: false } }) }
        disabled
        options={ [{ value: choice?.field ?? '', label: choice === undefined ? t('data-importer.ad-hoc-import.mapping.object-id') : titleOf(choice.field) }] }
        style={ { width: FIELD_WIDTH } }
        value={ choice?.field ?? '' }
      />
    )
  }

  return (
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
  )
}

export const MappingRowView = ({
  row,
  mapping,
  fields,
  languages,
  isActive,
  onActivate,
  onChange,
  onPickStoreKey
}: MappingRowViewProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { column, label, sample, auto, isMatch } = row

  return (
    <Flex
      align="center"
      aria-current={ isActive ? 'true' : undefined }
      className={ isActive ? styles.active : undefined }
      gap="small"
      onClick={ isMatch ? undefined : () => { onActivate(column) } }
      onFocus={ isMatch ? undefined : () => { onActivate(column) } }
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

      <FieldSelect
        fields={ fields }
        languages={ languages }
        mapping={ mapping }
        onChange={ onChange }
        onPickStoreKey={ onPickStoreKey }
        row={ row }
      />

      { isMatch
        ? <Tag>{ t('data-importer.ad-hoc-import.mapping.match') }</Tag>
        : (
          <ChoiceDetails
            languages={ languages }
            mapping={ mapping }
            onChange={ onChange }
            onPickStoreKey={ onPickStoreKey }
            row={ row }
          />
          ) }
      { auto && <Tag color="purple">{ t('data-importer.ad-hoc-import.mapping.auto') }</Tag> }
    </Flex>
  )
}
