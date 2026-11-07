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
import { ColumnPicker, FormKit, Text } from '@pimcore/studio-ui-bundle/components'
import { buildFieldGroups } from './field-groups'
import { type Mapping, type MappingField, type MappingRow } from './mapping-state'
import { canGoInto, takenFields } from './taken-fields'

// Studio's tree grows to its widest label; here it keeps the panel's width and long labels end in an ellipsis
const useStyles = createStyles(({ css }) => ({
  picker: css`
    min-width: 0;

    .ant-tree .ant-tree-list-holder-inner {
      min-width: 0;
    }

    .ant-tree .ant-tree-treenode {
      min-width: 0;
      max-width: 100%;
    }

    .ant-tree .ant-tree-node-content-wrapper {
      flex: 1 1 auto;
      min-width: 0;
      overflow: hidden;
    }

    .ant-tree .ant-tree-title,
    .ant-tree .ant-tree-title__btn {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
  `
}))

export interface FieldPanelProps {
  // the column whose field is picked; none while no column can take one
  row?: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  onPick: (row: MappingRow, field: MappingField) => void
}

export const FieldPanel = ({ row, mapping, fields, languages, onPick }: FieldPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const taken = useMemo(() => takenFields(mapping.rows), [mapping.rows])

  const groups = useMemo(() => row === undefined
    ? []
    : buildFieldGroups(fields, (field) => ({
        key: field.key,
        label: field.mandatory
          ? t('data-importer.ad-hoc-import.mapping.required-field', { field: field.title, interpolation: { escapeValue: false } })
          : field.title,
        disabled: !canGoInto(taken, field, row.column, languages),
        meta: field
      })), [row, fields, taken, languages, t])

  return (
    <FormKit.Panel
      title={ row === undefined
        ? t('data-importer.ad-hoc-import.mapping.fields')
        : t('data-importer.ad-hoc-import.mapping.fields-for', { column: row.label, interpolation: { escapeValue: false } }) }
    >
      { row === undefined
        ? <Text>{ t('data-importer.ad-hoc-import.mapping.pick-a-column') }</Text>
        : (
          <div className={ styles.picker }>
            <ColumnPicker
              emptyText={ t('data-importer.ad-hoc-import.mapping.no-fields') }
              groups={ groups }
              onSelect={ ({ meta }) => { if (meta !== undefined) { onPick(row, meta) } } }
              searchPlaceholder={ t('data-importer.ad-hoc-import.mapping.find-field') }
            />
          </div>
          ) }
    </FormKit.Panel>
  )
}
