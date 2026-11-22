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
const FIELD_COLUMN_SIZE = 64
const MODIFIED_COLUMN_SIZE = 112
// the header cell's border and the rounding of the measured text
const HEADER_SLACK = 4

// a header is never cut: its column is as wide as its text in the header's font and the cell's padding
export const fieldColumnSize = (textWidth: number, cellPadding: number): number =>
  Math.max(FIELD_COLUMN_SIZE, Math.ceil(textWidth) + 2 * cellPadding + HEADER_SLACK)

// the one column that takes the width the others leave: the one with the shortest header
export const autoWidthColumnOf = (widths: number[]): number => widths.indexOf(Math.min(...widths))

const useHeaderTextWidth = (): ((label: string) => number) => {
  const theme = useTheme()

  return useMemo(() => {
    const context = document.createElement('canvas').getContext('2d') ?? null
    if (context === null) {
      return () => 0
    }
    context.font = `${theme.fontWeightStrong} ${theme.fontSize}px ${theme.fontFamily}`

    return (label: string) => context.measureText(label).width
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
  const headerTextWidth = useHeaderTextWidth()
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
  const columns = useMemo<Array<ColumnDef<ImportedObject>>>(() => {
    const sizes = (fields ?? []).map((field) => fieldColumnSize(headerTextWidth(field.label), theme.paddingXS))
    const autoWidth = autoWidthColumnOf(sizes)

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
            // fixed to its header, so no header is cut; one column takes the width left and never shrinks below it
            size: sizes[position],
            ...(position === autoWidth ? { meta: { autoWidth: true } } : {})
          }))),
      {
        id: 'modified',
        header: t('data-importer.ad-hoc-import.saved-objects.modified'),
        accessorFn: (object: ImportedObject) => timeOf(object.modificationDate),
        // a fixed width, so the date stays on one line while the fields share the rest
        size: MODIFIED_COLUMN_SIZE
      }
    ]
  }, [fields, t, headerTextWidth, theme.paddingXS])

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
          theme="secondary"
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
