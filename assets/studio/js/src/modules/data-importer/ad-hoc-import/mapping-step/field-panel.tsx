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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { ColumnPicker, FormKit, Text } from '@pimcore/studio-ui-bundle/components'
import { buildFieldGroups } from './field-groups'
import { canGoInto, type Mapping, type MappingField, type MappingRow } from './mapping-state'

export interface FieldPanelProps {
  // the column whose field is picked; none while no column can take one
  row?: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  onPick: (field: MappingField) => void
}

export const FieldPanel = ({ row, mapping, fields, languages, onPick }: FieldPanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  const groups = useMemo(() => row === undefined
    ? []
    : buildFieldGroups(fields, (field) => ({
        key: field.key,
        label: field.mandatory
          ? t('data-importer.ad-hoc-import.mapping.required-field', { field: field.title, interpolation: { escapeValue: false } })
          : field.title,
        disabled: !canGoInto(mapping.rows, field, row.column, languages),
        meta: field
      })), [row, fields, mapping.rows, languages, t])

  return (
    <FormKit.Panel
      title={ row === undefined
        ? t('data-importer.ad-hoc-import.mapping.fields')
        : t('data-importer.ad-hoc-import.mapping.fields-for', { column: row.label, interpolation: { escapeValue: false } }) }
    >
      { row === undefined
        ? <Text>{ t('data-importer.ad-hoc-import.mapping.pick-a-column') }</Text>
        : (
          <ColumnPicker
            emptyText={ t('data-importer.ad-hoc-import.mapping.no-fields') }
            groups={ groups }
            onSelect={ ({ meta }) => { if (meta !== undefined) { onPick(meta) } } }
            searchPlaceholder={ t('data-importer.ad-hoc-import.mapping.find-field') }
          />
          ) }
    </FormKit.Panel>
  )
}
