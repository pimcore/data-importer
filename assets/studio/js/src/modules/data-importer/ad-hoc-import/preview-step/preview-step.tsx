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
  FOLD_SEPARATOR,
  foldedFields,
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

// the board's proportions inside the dialog, so every column fits without a horizontal scroll
const COLUMN_WIDTH = { row: 64, match: 120, action: 190, field: 160, rest: 240 }

// one line, cut off at the column's edge; the whole text on hover
const CellText = ({ className, value }: { className: string, value: string }): React.JSX.Element => (
  <span
    className={ className }
    title={ value }
  >
    { value }
  </span>
)

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

    const { shown, folded } = foldedFields(dryRun.fields)
    const header = (field: DryRun['fields'][number]): string => field.language === null ? field.label : `${field.label} (${field.language})`

    return [
      {
        id: 'row',
        header: t('data-importer.ad-hoc-import.preview.column.row'),
        accessorFn: (row) => String(row.row),
        cell: ({ getValue }) => (
          <CellText
            className={ `${styles.cell} ${styles.rowNumber}` }
            value={ getValue<string>() }
          />
        ),
        size: COLUMN_WIDTH.row
      },
      {
        id: 'match',
        header: dryRun.matchColumn,
        accessorFn: (row) => row.matchValue,
        cell: ({ getValue }) => (
          <CellText
            className={ `${styles.cell} ${styles.matchValue}` }
            value={ getValue<string>() }
          />
        ),
        size: COLUMN_WIDTH.match
      },
      {
        id: 'action',
        header: t('data-importer.ad-hoc-import.preview.column.action'),
        accessorFn: actionText,
        cell: ({ getValue }) => (
          <CellText
            className={ styles.cell }
            value={ getValue<string>() }
          />
        ),
        size: COLUMN_WIDTH.action
      },
      ...shown.map((field, position): ColumnDef<DryRunRow> => ({
        id: `field-${position}`,
        header: header(field),
        accessorFn: (row) => cellText(row, position),
        cell: ({ getValue }) => (
          <CellText
            className={ styles.cell }
            value={ getValue<string>() }
          />
        ),
        size: COLUMN_WIDTH.field
      })),
      {
        // the fields past the shown ones share the last column, whose place a row's message takes
        id: 'rest',
        header: folded.length === 0
          ? t('data-importer.ad-hoc-import.preview.column.message')
          : folded.map(header).join(FOLD_SEPARATOR),
        accessorFn: (row) => rowTone(row, errorMode) === 'none'
          ? folded.map((_, position) => cellText(row, shown.length + position)).join(FOLD_SEPARATOR)
          : messageText(row),
        cell: ({ getValue, row }) => {
          const value = String(getValue())
          const toneClass = {
            none: undefined,
            error: `${styles.error} ${ROW_ERROR_MARK}`,
            warning: `${styles.warning} ${ROW_WARNING_MARK}`
          }[rowTone(row.original, errorMode)]

          return (
            <span
              className={ styles.cell }
              title={ value }
            >
              <span className={ toneClass }>{ value }</span>
            </span>
          )
        },
        size: COLUMN_WIDTH.rest,
        meta: { autoWidth: true }
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
      <div className={ styles.table }>
        <Grid
          autoWidth
          className={ styles.grid }
          columns={ columns }
          data={ pageOf(rows, current, pageSize) }
          docked
          setRowId={ (row: DryRunRow) => String(row.row) }
          size="small"
        />
      </div>
      <Flex
        align="center"
        className={ styles.pagination }
        justify="flex-end"
      >
        <span className={ styles.total }>{ t('data-importer.ad-hoc-import.preview.total', { total: rows.length }) }</span>
        <Pagination
          current={ current }
          defaultPageSize={ PAGE_SIZE }
          onChange={ (nextPage: number, nextPageSize: number) => {
            setPage(nextPage)
            setPageSize(nextPageSize)
          } }
          showSizeChanger
          total={ rows.length }
        />
      </Flex>
    </div>
  )
}
