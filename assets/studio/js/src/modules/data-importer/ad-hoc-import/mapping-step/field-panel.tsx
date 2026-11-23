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
import { ColumnPicker } from '@pimcore/studio-ui-bundle/components'
import { buildFieldGroups } from './field-groups'
import { type Mapping, type MappingField, type MappingRow } from './mapping-state'
import { canGoInto, takenFields } from './taken-fields'

const PICKER_TEST_ID = 'ad-hoc-import-field-picker'

// Studio's tree grows to its widest label; here it keeps the panel's width and height, long labels end in an ellipsis,
// and a field another column already fills cannot be picked and ends in a green dot
const useStyles = createStyles(({ token, css }) => ({
  picker: css`
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;

    & > [data-testid='${PICKER_TEST_ID}'] {
      width: 100%;
    }

    /* the field search is a plain field, without the magnifier ColumnPicker puts before it */
    & > [data-testid='${PICKER_TEST_ID}'] .ant-input-affix-wrapper .ant-input-prefix {
      display: none;
    }

    .ant-tree .ant-tree-list-holder-inner {
      min-width: 0;
    }

    .ant-tree .ant-tree-treenode {
      min-width: 0;
      max-width: 100%;
    }

    /* every row centres its label at the design's 26px, so a row with the dot keeps the height of one without */
    .ant-tree .ant-tree-node-content-wrapper {
      min-height: 26px;
      display: flex;
      flex: 1 1 auto;
      align-items: center;
      gap: ${token.marginXS}px;
      min-width: 0;
      overflow: hidden;
    }

    .ant-tree .ant-tree-title {
      flex: 1 1 auto;
      min-width: 0;
    }

    .ant-tree .ant-tree-title,
    .ant-tree .ant-tree-title__btn {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }

    /* one text colour for every field, the groups in bold as in the design */
    .ant-tree .ant-tree-title__btn {
      color: ${token.colorText};
    }

    .ant-tree .ant-tree-title > :not(.ant-tree-title__btn) {
      font-weight: ${token.fontWeightStrong};
    }

    /* the field stays readable as in the design; the dot and the cursor say another column fills it */
    .ant-tree .ant-tree-treenode-disabled .ant-tree-title__btn {
      color: ${token.colorText};
      cursor: not-allowed;
    }

    /* a field another column fills ends in a green dot */
    .ant-tree .ant-tree-treenode-disabled .ant-tree-node-content-wrapper::after {
      content: '';
      flex: 0 0 6px;
      height: 6px;
      margin-inline-end: ${token.marginSM}px;
      border-radius: 50%;
      background: ${token.colorSuccess};
    }
  `
}))

export interface FieldPanelProps {
  // the column whose field is picked
  row: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  onPick: (row: MappingRow, field: MappingField) => void
}

export const FieldPanel = ({ row, mapping, fields, languages, onPick }: FieldPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const taken = useMemo(() => takenFields(mapping.rows), [mapping.rows])

  const groups = useMemo(() => buildFieldGroups(fields, (field) => ({
    key: field.key,
    label: field.mandatory
      ? t('data-importer.ad-hoc-import.mapping.required-field', { field: field.title, interpolation: { escapeValue: false } })
      : field.title,
    disabled: !canGoInto(taken, field, row.column, languages),
    meta: field
  })), [row, fields, taken, languages, t])

  return (
    <div className={ styles.picker }>
      <ColumnPicker
        data-testid={ PICKER_TEST_ID }
        emptyText={ t('data-importer.ad-hoc-import.mapping.no-fields') }
        fillHeight
        groups={ groups }
        onSelect={ ({ meta }) => { if (meta !== undefined) { onPick(row, meta) } } }
        searchPlaceholder={ t('data-importer.ad-hoc-import.mapping.find-field') }
      />
    </div>
  )
}
