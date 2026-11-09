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
import { Alert, Grid } from '@pimcore/studio-ui-bundle/components'
import { type ImportRun, type ImportRunFailure } from './import-state'
import { useStyles } from './import-step.styles'

export const FailuresTable = ({ run, why }: { run: ImportRun, why: string }): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const columns = useMemo<Array<ColumnDef<ImportRunFailure>>>(() => [
    {
      id: 'row',
      header: t('data-importer.ad-hoc-import.result.column.row'),
      accessorFn: (failure) => String(failure.row),
      size: 64
    },
    {
      id: 'match',
      header: run.matchColumn,
      accessorFn: (failure) => failure.matchValue
    },
    {
      id: 'action',
      header: t('data-importer.ad-hoc-import.result.column.action'),
      accessorFn: (failure) => failure.action === null ? '' : t(`data-importer.ad-hoc-import.preview.action.${failure.action}`)
    },
    {
      id: 'why',
      header: why,
      accessorFn: (failure) => failure.message,
      // a cell of one's own loses the grid's padding
      cell: ({ getValue }) => <span className={ styles.error }>{ getValue<string>() }</span>
    }
  ], [run.matchColumn, styles.error, t, why])

  return (
    <Grid
      autoWidth
      columns={ columns }
      data={ run.failures }
      setRowId={ (failure: ImportRunFailure) => String(failure.row) }
    />
  )
}

// a summary on one line: what happened in bold, then what it means
export const SummaryLine = ({ lead, rest }: { lead: string, rest: string }): React.JSX.Element => (
  <span>
    <strong>{ lead }</strong>
    { ' ' }
    { rest }
  </span>
)

// what went wrong that no row of the file shows
export const Notice = ({ run }: { run: ImportRun }): React.JSX.Element | null => {
  const { styles } = useStyles()

  return run.notice === null || run.notice === undefined
    ? null
    : (
      <Alert
        className={ styles.notSaved }
        message={ run.notice }
        showIcon
        type="warning"
      />
      )
}
