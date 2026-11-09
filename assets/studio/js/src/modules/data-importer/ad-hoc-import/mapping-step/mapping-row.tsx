/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Flex, Icon, LanguageSelection, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { chooseField, fitsField, type Mapping, type MappingField, type MappingRow } from './mapping-state'
import { chooseLanguage, freeLanguages } from './mapping-choices'
import { ROW_COLUMNS, useStyles } from './mapping-row.styles'

const MAPPING = 'data-importer.ad-hoc-import.mapping'

const transformationsLabel = (t: ReturnType<typeof useTranslation>['t'], count: number): string => {
  if (count === 0) {
    return t(`${MAPPING}.transform`)
  }

  return count === 1 ? t(`${MAPPING}.transformation-one`) : t(`${MAPPING}.transformations-count`, { count })
}

export interface MappingRowViewProps {
  row: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  // the column the side panel is open on
  isActive: boolean
  onChange: (mapping: Mapping) => void
  // a classification store goes in by a key chosen in its own dialog
  onPickStoreKey: (column: string, field: string) => void
  // the side panel, on the column's field or its transformations; none without a file to run them on
  onOpenField: (column: string) => void
  onOpenTransformations?: (column: string) => void
  onSkip: (column: string) => void
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
          aria-label={ t(`${MAPPING}.change-key`, { column: label, interpolation: { escapeValue: false } }) }
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

const iconOf = (field: MappingField | undefined): string => {
  if (field?.classificationStore === true) {
    return 'classification-store'
  }
  if (field?.key.includes('.') === true) {
    return 'object-bricks'
  }

  return field?.localized === true ? 'world' : 'text-field'
}

type FieldCellProps = Pick<MappingRowViewProps, 'row' | 'mapping' | 'fields' | 'languages' | 'onChange' | 'onOpenField' | 'onPickStoreKey'>

const FieldCell = ({ row, mapping, fields, languages, onChange, onOpenField, onPickStoreKey }: FieldCellProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { column, label, choice, suggestion, isMatch, auto } = row
  const field = fields.find(({ key }) => key === choice?.field)
  const openLabel = t(`${MAPPING}.field-of`, { column: label, interpolation: { escapeValue: false } })

  if (isMatch) {
    return (
      <Flex
        align="center"
        gap="small"
      >
        <span className={ styles.fieldName }>{ field?.title ?? t(`${MAPPING}.object-id`) }</span>
        <Tag>{ t(`${MAPPING}.match`) }</Tag>
      </Flex>
    )
  }

  if (choice === undefined) {
    const suggested = fields.find(({ key }) => key === suggestion?.field)

    return (
      <Flex
        align="center"
        gap="small"
        wrap="wrap"
      >
        <button
          aria-label={ openLabel }
          className={ styles.fieldButton }
          onClick={ () => { onOpenField(column) } }
          type="button"
        >
          <span className={ styles.crumb }>{ t(`${MAPPING}.choose-field`) }</span>
        </button>
        { suggestion !== undefined && suggested !== undefined && (
          <button
            className={ styles.suggestion }
            onClick={ () => { onChange(chooseField(mapping, column, suggestion.field)) } }
            type="button"
          >
            { t(`${MAPPING}.use-suggestion`, { field: suggested.title, interpolation: { escapeValue: false } }) }
          </button>
        ) }
      </Flex>
    )
  }

  return (
    <Flex
      align="center"
      gap="small"
    >
      <button
        aria-label={ openLabel }
        className={ styles.fieldButton }
        onClick={ () => { onOpenField(column) } }
        type="button"
      >
        <span className={ styles.fieldIcon }><Icon value={ iconOf(field) } /></span>
        <span className={ styles.fieldName }>{ field?.title ?? choice.field }</span>
        <span className={ styles.crumb }>{ field?.group.join(' › ') }</span>
        { auto && (
          <Tag
            color="purple"
            iconName="auto-save"
          >
            { t(`${MAPPING}.auto`) }
          </Tag>
        ) }
      </button>
      <ChoiceDetails
        languages={ languages }
        mapping={ mapping }
        onChange={ onChange }
        onPickStoreKey={ onPickStoreKey }
        row={ row }
      />
    </Flex>
  )
}

export const MappingRowView = ({
  row,
  mapping,
  fields,
  languages,
  isActive,
  onChange,
  onPickStoreKey,
  onOpenField,
  onOpenTransformations,
  onSkip
}: MappingRowViewProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { column, label, sample, choice, isMatch, transformations } = row
  const isMapped = isMatch || choice !== undefined

  return (
    <div
      aria-current={ isActive ? 'true' : undefined }
      className={ isActive ? `${styles.row} ${styles.active}` : styles.row }
      role="listitem"
    >
      <div className={ styles.dotCell }>
        <span className={ `${styles.dot} ${isMapped ? styles.dotMapped : styles.dotOpen}` } />
      </div>
      <Flex
        className={ styles.cell }
        vertical
      >
        <Text
          ellipsis
          strong
        >
          { label }
        </Text>
        { sample !== undefined && (
          <span className={ styles.sample }>
            { t(`${MAPPING}.sample`, { value: sample, interpolation: { escapeValue: false } }) }
          </span>
        ) }
      </Flex>
      <span
        aria-hidden
        className={ styles.arrow }
      >
        →
      </span>
      <div className={ styles.cell }>
        <FieldCell
          fields={ fields }
          languages={ languages }
          mapping={ mapping }
          onChange={ onChange }
          onOpenField={ onOpenField }
          onPickStoreKey={ onPickStoreKey }
          row={ row }
        />
      </div>
      <div className={ styles.cell }>
        { !isMatch && choice !== undefined && choice.key === undefined && onOpenTransformations !== undefined && (
          <Button
            aria-label={ t(`${MAPPING}.transformations-of`, { column: label, interpolation: { escapeValue: false } }) }
            onClick={ () => { onOpenTransformations(column) } }
            size="small"
            type={ transformations.length === 0 ? 'default' : 'primary' }
          >
            { transformationsLabel(t, transformations.length) }
          </Button>
        ) }
        { !fitsField(row, fields) && <Tag color="warning">{ t(`${MAPPING}.needs-transformation`) }</Tag> }
      </div>
      <div className={ styles.skip }>
        { !isMatch && (
          <Button
            aria-label={ t(`${MAPPING}.skip-column`, { column: label, interpolation: { escapeValue: false } }) }
            onClick={ () => { onSkip(column) } }
            type="text"
          >
            { t(`${MAPPING}.skip`) }
          </Button>
        ) }
      </div>
    </div>
  )
}
