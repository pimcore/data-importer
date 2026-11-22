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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Content, ContentLayout, Grid, Pagination, Segmented, Text, Toolbar } from '@pimcore/studio-ui-bundle/components'
import {
  useBundleDataImporterAdHocImportListRunObjectsQuery,
  type BundleDataImporterAdHocImportRunObjectsResponse
} from '../../data-importer-api-slice-enhanced'
import { objectsExportUrl, shortTimeOf, type ObjectsKind } from '../import-step/import-state'

export const PAGE_SIZE = 50
const FIELD_COLUMN_SIZE = 80
const MODIFIED_COLUMN_SIZE = 120

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
  // a narrow tab wraps the info line instead of cutting it, so the export stays in view
  bar: css`
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
    width: 100%;
    min-width: 0;
  `,
  info: css`
    min-width: 0;
    overflow-wrap: anywhere;
  `,
  noShrink: css`
    flex-shrink: 0;
  `,
  spacer: css`
    flex-grow: 1;
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
  const timeOf = (timestamp: number | null): string => shortTimeOf(timestamp, (time) => t('data-importer.ad-hoc-import.result.today', { time }))
  const [kind, setKind] = useState<ObjectsKind>(firstKind)
  const [page, setPage] = useState(1)
  const { data, isLoading, isFetching, isError } = useBundleDataImporterAdHocImportListRunObjectsQuery({ fileId, kind, page, pageSize: PAGE_SIZE })

  const fields = data?.fields
  const columns = useMemo<Array<ColumnDef<ImportedObject>>>(() => [
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
          // every column shares the width; an auto width column never shrinks below its size
          size: FIELD_COLUMN_SIZE,
          meta: { autoWidth: true }
        }))),
    {
      id: 'modified',
      header: t('data-importer.ad-hoc-import.saved-objects.modified'),
      accessorFn: (object: ImportedObject) => timeOf(object.modificationDate),
      size: MODIFIED_COLUMN_SIZE,
      meta: { autoWidth: true }
    }
  ], [fields, t])

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
            <Segmented
              className={ styles.noShrink }
              onChange={ (value: string) => { choose(value as ObjectsKind) } }
              options={ [
                { value: 'updated', label: t('data-importer.ad-hoc-import.saved-objects.updated', { count: updated }), disabled: updated === 0 },
                { value: 'created', label: t('data-importer.ad-hoc-import.saved-objects.created', { count: created }), disabled: created === 0 }
              ] }
              value={ kind }
            />
            <Text
              className={ styles.info }
              type="secondary"
            >
              { t('data-importer.ad-hoc-import.saved-objects.imported-by', {
                time: timeOf(finishedAt),
                user: userName,
                interpolation: { escapeValue: false }
              }) }
            </Text>
            <span className={ styles.spacer } />
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
              allowMultipleAutoWidthColumns
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
