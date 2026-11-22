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
import { type ColumnDef } from '@tanstack/react-table'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Grid } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from './file-step-state'

const VISIBLE_COLUMNS = 6
const MORE_COLUMN_WIDTH = 72

type Row = Array<string | null>

const ROW_HEIGHT = 29

const useStyles = createStyles(({ token, css }) => ({
  // a note on the columns left out, not a column name
  more: css`
    font-weight: normal;
    color: ${token.colorTextDescription};
  `,
  // a glance at the file, not a grid to work in: no lines between the columns and the rows as low as the text allows
  rows: css`
    .ant-table-wrapper .ant-table-cell:not(:first-child) {
      border-left: 0;
    }

    .ant-table-wrapper .ant-table-thead > tr > th.ant-table-cell::before {
      display: none;
    }

    .ant-table-wrapper .ant-table-row {
      height: ${ROW_HEIGHT}px;
    }
  `
}))

export interface FirstRowsProps {
  inspected: InspectedFile
  isLoading: boolean
}

export const FirstRows = ({ inspected, isLoading }: FirstRowsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const hiddenColumns = inspected.columns.length - VISIBLE_COLUMNS

  const columns = useMemo<Array<ColumnDef<Row>>>(() => {
    const visible: Array<ColumnDef<Row>> = inspected.columns.slice(0, VISIBLE_COLUMNS).map(({ label }, position) => ({
      id: `column-${position}`,
      header: label,
      accessorFn: (row) => row[position] ?? ''
    }))

    if (hiddenColumns <= 0) {
      return visible
    }

    return [...visible, {
      id: 'more-columns',
      header: () => <span className={ styles.more }>{ t('data-importer.ad-hoc-import.first-rows.more-columns', { count: hiddenColumns }) }</span>,
      accessorFn: () => '',
      size: MORE_COLUMN_WIDTH
    }]
  }, [inspected.columns, hiddenColumns, styles.more, t])

  return (
    <div className={ styles.rows }>
      <Grid
        autoWidth
        columns={ columns }
        data={ inspected.firstRows }
        isLoading={ isLoading }
        size="small"
      />
    </div>
  )
}
