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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Grid } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from './file-step-state'

const VISIBLE_COLUMNS = 6
const MORE_COLUMN_WIDTH = 72

type Row = Array<string | null>

export interface FirstRowsProps {
  inspected: InspectedFile
  isLoading: boolean
}

export const FirstRows = ({ inspected, isLoading }: FirstRowsProps): React.JSX.Element => {
  const { t } = useTranslation()

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
      header: t('data-importer.ad-hoc-import.first-rows.more-columns', { count: hiddenColumns }),
      accessorFn: () => '',
      size: MORE_COLUMN_WIDTH
    }]
  }, [inspected.columns, hiddenColumns, t])

  return (
    <Grid
      autoWidth
      columns={ columns }
      data={ inspected.firstRows }
      isLoading={ isLoading }
    />
  )
}
