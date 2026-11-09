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
import { type ColumnDef } from '@tanstack/react-table'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Flex, Grid, Pagination, Skeleton, Switch } from '@pimcore/studio-ui-bundle/components'
import {
  cellText,
  isSkipped,
  lastPageWithin,
  PAGE_SIZE,
  pageOf,
  previewCounts,
  rowTone,
  visibleRows,
  type DryRun,
  type DryRunRow,
  type ErrorMode
} from './preview-state'
import { ROW_ERROR_MARK, ROW_WARNING_MARK, useStyles } from './preview-step.styles'

export interface PreviewStepProps {
  dryRun: DryRun | null
  errorMode: ErrorMode
}

export const PreviewStep = ({ dryRun, errorMode }: PreviewStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const [errorsOnly, setErrorsOnly] = useState(false)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(PAGE_SIZE)

  const rows = useMemo(() => dryRun === null ? [] : visibleRows(dryRun, errorsOnly), [dryRun, errorsOnly])
  const current = lastPageWithin(page, rows.length, pageSize)

  const columns = useMemo<Array<ColumnDef<DryRunRow>>>(() => {
    if (dryRun === null) {
      return []
    }

    const actionText = (row: DryRunRow): string => {
      const tone = rowTone(row, errorMode)
      if (row.action === null || tone === 'error') {
        return t('data-importer.ad-hoc-import.preview.action.skipped')
      }
      if (tone === 'warning') {
        return t(`data-importer.ad-hoc-import.preview.action.${row.action}-without`, {
          fields: row.invalid.map(({ label }) => label).join(', '),
          interpolation: { escapeValue: false }
        })
      }

      return t(`data-importer.ad-hoc-import.preview.action.${row.action}`)
    }

    const messageText = (row: DryRunRow): string => {
      if (row.error !== null) {
        return row.error
      }

      return row.invalid.map(({ label, value, message }) => isSkipped(row, errorMode)
        ? message
        : t('data-importer.ad-hoc-import.preview.left-unchanged', {
            field: label,
            value,
            interpolation: { escapeValue: false }
          })).join('; ')
    }

    return [
      {
        id: 'row',
        header: t('data-importer.ad-hoc-import.preview.column.row'),
        accessorFn: (row) => String(row.row),
        size: 64
      },
      {
        id: 'match',
        header: dryRun.matchColumn,
        accessorFn: (row) => row.matchValue,
        cell: ({ getValue }) => <span className={ `${styles.cell} ${styles.matchValue}` }>{ getValue<string>() }</span>
      },
      {
        id: 'action',
        header: t('data-importer.ad-hoc-import.preview.column.action'),
        accessorFn: actionText
      },
      ...dryRun.fields.map((field, position): ColumnDef<DryRunRow> => ({
        id: `field-${position}`,
        header: field.language === null ? field.label : `${field.label} (${field.language})`,
        accessorFn: (row) => cellText(row, position)
      })),
      {
        id: 'message',
        header: t('data-importer.ad-hoc-import.preview.column.message'),
        accessorFn: messageText,
        cell: ({ getValue, row }) => {
          const tone = rowTone(row.original, errorMode)

          const toneClass = {
            none: undefined,
            error: `${styles.error} ${ROW_ERROR_MARK}`,
            warning: `${styles.warning} ${ROW_WARNING_MARK}`
          }[tone]

          return <span className={ styles.cell }><span className={ toneClass }>{ getValue<string>() }</span></span>
        }
      }
    ]
  }, [dryRun, errorMode, styles, t])

  if (dryRun === null) {
    return <Skeleton active />
  }

  const counts = previewCounts(dryRun, errorMode)

  return (
    <div>
      <div className={ styles.counts }>
        <div className={ styles.count }>
          <span className={ styles.number }>{ counts.updated }</span>
          <span className={ styles.label }>{ t('data-importer.ad-hoc-import.preview.updated') }</span>
        </div>
        <div className={ styles.count }>
          <span className={ styles.number }>{ counts.created }</span>
          <span className={ styles.label }>
            { t('data-importer.ad-hoc-import.preview.created', {
              folder: dryRun.newObjectsFolder,
              interpolation: { escapeValue: false }
            }) }
          </span>
        </div>
        <div className={ counts.withErrors > 0 ? `${styles.count} ${styles.countWithErrors}` : styles.count }>
          <span className={ styles.number }>{ counts.withErrors }</span>
          <span>{ t(`data-importer.ad-hoc-import.preview.with-errors.${errorMode}`) }</span>
        </div>
      </div>
      <div className={ styles.checked }>
        <span className={ styles.checkedTitle }>
          { t('data-importer.ad-hoc-import.preview.checked', { count: dryRun.rows.length }) }
        </span>
        <span className={ styles.hint }>{ t('data-importer.ad-hoc-import.preview.old-new') }</span>
        <Switch
          checked={ errorsOnly }
          labelRight={ t('data-importer.ad-hoc-import.preview.errors-only') }
          onChange={ (checked: boolean) => { setErrorsOnly(checked); setPage(1) } }
          size="small"
        />
      </div>
      <Grid
        autoWidth
        className={ styles.grid }
        columns={ columns }
        data={ pageOf(rows, current, pageSize) }
        setRowId={ (row: DryRunRow) => String(row.row) }
      />
      <Flex
        className={ styles.pagination }
        justify="flex-end"
      >
        <Pagination
          current={ current }
          defaultPageSize={ PAGE_SIZE }
          onChange={ (nextPage: number, nextPageSize: number) => {
            setPage(nextPage)
            setPageSize(nextPageSize)
          } }
          showSizeChanger
          showTotal={ (total: number) => t('data-importer.ad-hoc-import.preview.total', { total }) }
          total={ rows.length }
        />
      </Flex>
    </div>
  )
}
