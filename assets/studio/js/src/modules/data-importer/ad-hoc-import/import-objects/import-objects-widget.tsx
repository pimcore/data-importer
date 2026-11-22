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
import { ConfigProvider } from 'antd'
import { useTheme } from 'antd-style'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Content, ContentLayout, Grid, Pagination, Segmented, Text, Toolbar } from '@pimcore/studio-ui-bundle/components'
import {
  useBundleDataImporterAdHocImportListRunObjectsQuery,
  type BundleDataImporterAdHocImportRunObjectsResponse
} from '../../data-importer-api-slice-enhanced'
import { objectsExportUrl, shortTimeOf, type ObjectsKind } from '../import-step/import-state'

export const PAGE_SIZE = 50
const FIELD_COLUMN_SIZE = 48
// the cell's border and the rounding of the measured text
const CELL_SLACK = 4

// neither a header nor a value is cut or wrapped: a column is as wide as its widest text and the cell's padding
export const fieldColumnSize = (textWidth: number, cellPadding: number): number =>
  Math.max(FIELD_COLUMN_SIZE, Math.ceil(textWidth) + 2 * cellPadding + CELL_SLACK)

interface TextWidth {
  header: (text: string) => number
  cell: (text: string) => number
}

const useTextWidth = (): TextWidth => {
  const theme = useTheme()

  return useMemo(() => {
    const context = document.createElement('canvas').getContext('2d') ?? null
    if (context === null) {
      return { header: () => 0, cell: () => 0 }
    }
    const measure = (weight: number | string) => (text: string): number => {
      context.font = `${weight} ${theme.fontSize}px ${theme.fontFamily}`

      return context.measureText(text).width
    }

    return { header: measure(theme.fontWeightStrong), cell: measure('normal') }
  }, [theme.fontWeightStrong, theme.fontSize, theme.fontFamily])
}

type ImportedObject = BundleDataImporterAdHocImportRunObjectsResponse['items'][number]

export interface ImportObjectsWidgetProps {
  fileId: string
  fileName: string
  userName: string
  finishedAt: number | null
  updated: number
  created: number
  kind: ObjectsKind
}

const useStyles = createStyles(({ token, css }) => ({
  // one row, so the export stays in view beside the info line
  bar: css`
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
    width: 100%;
    min-width: 0;
  `,
  // one line that takes the room left and ends in an ellipsis where it does not fit, the whole text on hover
  info: css`
    flex: 1 1 auto;
    min-width: 0;
  `,
  noShrink: css`
    flex-shrink: 0;
  `,
  kinds: css`
    flex-shrink: 0;

    .ant-segmented-item-selected {
      font-weight: ${token.fontWeightStrong};
    }
  `,
  footer: css`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: ${token.marginSM}px;
    width: 100%;
    min-width: 0;
  `
}))

/**
 * The objects an ad hoc import updated or created, with the fields its file wrote.
 */
export const ImportObjectsWidget = ({ fileId, userName, finishedAt, updated, created, kind: firstKind }: ImportObjectsWidgetProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const theme = useTheme()
  const textWidth = useTextWidth()
  const timeOf = (timestamp: number | null): string => shortTimeOf(timestamp, (time) => t('data-importer.ad-hoc-import.result.today', { time }))
  const importedBy = t('data-importer.ad-hoc-import.saved-objects.imported-by', {
    time: timeOf(finishedAt),
    user: userName,
    interpolation: { escapeValue: false }
  })
  const [kind, setKind] = useState<ObjectsKind>(firstKind)
  const [page, setPage] = useState(1)
  const { data, isLoading, isFetching, isError } = useBundleDataImporterAdHocImportListRunObjectsQuery({ fileId, kind, page, pageSize: PAGE_SIZE })

  const fields = data?.fields
  const items = data?.items
  const columns = useMemo<Array<ColumnDef<ImportedObject>>>(() => {
    const sizeOf = (header: string, values: string[]): number =>
      fieldColumnSize(Math.max(textWidth.header(header), ...values.map(textWidth.cell)), theme.paddingXS)
    const sizes = (fields ?? []).map((field, position) =>
      sizeOf(field.label, (items ?? []).map((object) => String(object.values[position] ?? ''))))
    const modifiedHeader = t('data-importer.ad-hoc-import.saved-objects.modified')

    return [
      ...(fields === undefined || fields.length === 0
        ? [{
            id: 'path',
            header: t('data-importer.ad-hoc-import.saved-objects.path'),
            accessorFn: (object: ImportedObject) => object.fullPath,
            meta: { autoWidth: true }
          }]
        : fields.map((field, position) => ({
            id: `field-${position}`,
            header: field.label,
            accessorFn: (object: ImportedObject) => object.values[position] ?? '',
            // at least its widest text, so nothing is cut or wrapped; the full-width table shares the width left by these sizes
            size: sizes[position]
          }))),
      {
        id: 'modified',
        header: modifiedHeader,
        accessorFn: (object: ImportedObject) => timeOf(object.modificationDate),
        size: sizeOf(modifiedHeader, (items ?? []).map((object) => timeOf(object.modificationDate)))
      }
    ]
  }, [fields, items, t, textWidth, theme.paddingXS])

  // the chosen kind in the primary colour, as a filter that is on; Studio's Segmented draws its selection from these
  const selectedKind = { colorFill: theme.colorPrimary, itemSelectedColor: theme.colorTextLightSolid }

  const choose = (next: ObjectsKind): void => {
    setKind(next)
    setPage(1)
  }

  return (
    <ContentLayout
      renderToolbar={
        <Toolbar
          position="bottom"
          size="auto"
          theme="secondary"
        >
          <div className={ styles.footer }>
            <Text
              className={ styles.info }
              ellipsis={ { tooltip: t('data-importer.ad-hoc-import.saved-objects.filtered') } }
              type="secondary"
            >
              { t('data-importer.ad-hoc-import.saved-objects.filtered') }
            </Text>
            <Pagination
              className={ styles.noShrink }
              current={ page }
              defaultPageSize={ PAGE_SIZE }
              onChange={ (next: number) => { setPage(next) } }
              showSizeChanger={ false }
              showTotal={ (total: number) => t('data-importer.ad-hoc-import.preview.total', { total }) }
              total={ data?.totalItems ?? 0 }
            />
          </div>
        </Toolbar>
      }
      renderTopBar={
        <Toolbar
          position="top"
          size="auto"
        >
          <div className={ styles.bar }>
            <ConfigProvider theme={ { token: selectedKind } }>
              <Segmented
                className={ styles.kinds }
                onChange={ (value: string) => { choose(value as ObjectsKind) } }
                options={ [
                  { value: 'updated', label: t('data-importer.ad-hoc-import.saved-objects.updated', { count: updated }), disabled: updated === 0 },
                  { value: 'created', label: t('data-importer.ad-hoc-import.saved-objects.created', { count: created }), disabled: created === 0 }
                ] }
                value={ kind }
              />
            </ConfigProvider>
            <Text
              className={ styles.info }
              ellipsis={ { tooltip: importedBy } }
              type="secondary"
            >
              { importedBy }
            </Text>
            <Button
              className={ styles.noShrink }
              href={ objectsExportUrl(fileId, kind) }
            >
              { t('data-importer.ad-hoc-import.saved-objects.export') }
            </Button>
          </div>
        </Toolbar>
      }
    >
      <Content
        loading={ isLoading }
        padded
      >
        { isError
          ? (
            <Alert
              message={ t('data-importer.ad-hoc-import.saved-objects.gone') }
              showIcon
              type="warning"
            />
            )
          : (
            <Grid
              autoWidth
              columns={ columns }
              data={ data?.items ?? [] }
              isLoading={ isFetching }
              setRowId={ (object: ImportedObject) => String(object.id) }
              size="small"
            />
            ) }
      </Content>
    </ContentLayout>
  )
}
