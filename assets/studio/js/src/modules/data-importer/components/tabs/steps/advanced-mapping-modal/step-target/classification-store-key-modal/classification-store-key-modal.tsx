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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import {
  Button,
  Grid,
  Modal,
  Pagination,
  SearchInput,
  Text
} from '@pimcore/studio-ui-bundle/components'
import { createColumnHelper, type ColumnDef } from '@tanstack/react-table'
import { useBundleDataImporterClassificationstoreLoadKeysQuery } from '../../../../../../data-importer-api-slice.gen'
import { useStyles } from './classification-store-key-modal.styles'

interface ClassificationStoreKeyRow {
  id?: string
  keyId?: number
  groupId?: number
  keyName?: string
  keyDescription?: string
  groupName?: string
}

export interface ClassificationStoreKeyModalProps {
  open: boolean
  classId: string
  fieldName: string
  transformationResultType: string
  onClose: () => void
  onSelect: (keyId: string) => void
}

const columnHelper = createColumnHelper<ClassificationStoreKeyRow>()

export const ClassificationStoreKeyModal = ({
  open,
  classId,
  fieldName,
  transformationResultType,
  onClose,
  onSelect
}: ClassificationStoreKeyModalProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const [searchTerm, setSearchTerm] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [selectedRowId, setSelectedRowId] = useState<string | null>(null)

  const start = (page - 1) * pageSize

  const { data, isFetching } = useBundleDataImporterClassificationstoreLoadKeysQuery(
    {
      classId,
      fieldName,
      transformationResultType,
      start,
      limit: pageSize,
      searchfilter: searchTerm !== '' ? searchTerm : undefined
    },
    {
      skip: !open
    }
  )

  const rows = data?.data ?? []
  const total = data?.total ?? 0

  const columns = useMemo<Array<ColumnDef<ClassificationStoreKeyRow>>>(
    () => [
      columnHelper.accessor('groupName', {
        header: t('classification-store.column.group')
      }) as ColumnDef<ClassificationStoreKeyRow>,
      columnHelper.accessor('keyName', {
        header: t('classification-store.column.name')
      }) as ColumnDef<ClassificationStoreKeyRow>,
      columnHelper.accessor('keyDescription', {
        header: t('classification-store.column.description')
      }) as ColumnDef<ClassificationStoreKeyRow>
    ],
    [t]
  )

  const selectedRows = selectedRowId !== null ? { [selectedRowId]: true } : undefined

  return (
    <Modal
      footer={ null }
      onCancel={ onClose }
      open={ open }
      size="XL"
      title={ t('data-importer.mapping.advanced-modal.step-target.classification-store-key-modal.title') }
    >
      <div className={ styles.toolbar }>
        <Text>{ t('data-importer.mapping.advanced-modal.step-target.classification-store-key-modal.description') }</Text>
        <SearchInput
          className={ styles.search }
          onChange={ (event) => {
            setSearchTerm(event.target.value)
            setPage(1)
          } }
          placeholder={ t('data-importer.mapping.advanced-modal.step-source.search-placeholder') }
          value={ searchTerm }
          withClear
          withPrefix
        />
      </div>

      <Grid
        columns={ columns }
        data={ rows }
        isLoading={ isFetching }
        onSelectedRowsChange={ (selection) => {
          const firstKey = Object.keys(selection)[0]
          setSelectedRowId(firstKey ?? null)
        } }
        selectedRows={ selectedRows }
        setRowId={ (row) => row.id ?? '' }
      />

      <div className={ styles.paginationRow }>
        <Pagination
          current={ page }
          defaultPageSize={ pageSize }
          onChange={ (nextPage, nextPageSize) => {
            setPage(nextPage)
            setPageSize(nextPageSize)
          } }
          showSizeChanger
          showTotal={ (count) => t('pagination.show-total', { total: count }) }
          total={ total }
        />

      </div>

      <div className={ styles.footer }>
        <Button
          onClick={ onClose }
          type="default"
        >
          { t('common.cancel') }
        </Button>
        <Button
          disabled={ selectedRowId === null }
          onClick={ () => {
            if (selectedRowId !== null) {
              onSelect(selectedRowId)
            }
          } }
          type="primary"
        >
          { t('common.apply-selection') }
        </Button>
      </div>
    </Modal>
  )
}
