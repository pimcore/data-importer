/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo, useState } from 'react'
import { useAppDispatch, useTranslation } from '@pimcore/studio-ui-bundle/app'
import {
  Button,
  Flex,
  Grid,
  Modal,
  Pagination,
  SearchInput,
  Text
} from '@pimcore/studio-ui-bundle/components'
import { createColumnHelper, type ColumnDef, type RowSelectionState } from '@tanstack/react-table'
import { useBundleDataImporterClassificationstoreLoadKeysQuery } from '../../../../../../data-importer-api-slice.gen'
import { api } from '../../../../../../data-importer-api-slice-enhanced'
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
  const dispatch = useAppDispatch()

  const [searchTerm, setSearchTerm] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [selectedRowId, setSelectedRowId] = useState<string | null>(null)

  // Start fresh every time the modal is opened (clear search, paging and selection).
  useEffect(() => {
    if (open) {
      setSearchTerm('')
      setPage(1)
      setSelectedRowId(null)
    }
  }, [open])

  const start = (page - 1) * pageSize

  const { data, isFetching } = useBundleDataImporterClassificationstoreLoadKeysQuery(
    {
      classId,
      fieldName,
      transformationResultType,
      start,
      limit: pageSize,
      searchfilter: searchTerm === '' ? undefined : searchTerm
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

  const selectedRows: RowSelectionState = selectedRowId === null ? {} : { [selectedRowId]: true }

  return (
    <Modal
      footer={ null }
      onCancel={ onClose }
      open={ open }
      size="XL"
      title={ t('data-importer.mapping.advanced-modal.step-target.classification-store-key-modal.title') }
    >
      <Flex
        align="center"
        className={ styles.toolbar }
        gap="small"
        justify="space-between"
      >
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
      </Flex>

      <Grid
        autoWidth
        columns={ columns }
        data={ rows }
        enableMultipleRowSelection={ false }
        enableRowSelection
        isLoading={ isFetching }
        onSelectedRowsChange={ (selection) => {
          // The Grid forwards tanstack's raw updater here (selection is controlled),
          // so resolve it against the current state before reading the selected id.
          const updater = selection as unknown as
            RowSelectionState | ((old: RowSelectionState) => RowSelectionState)
          const next = typeof updater === 'function' ? updater(selectedRows) : updater
          const selectedId = Object.keys(next).find((key) => next[key])
          setSelectedRowId(selectedId ?? null)
        } }
        selectedRows={ selectedRows }
        setRowId={ (row, index) => row.id ?? String(index) }
      />

      <Flex
        className={ styles.paginationRow }
        justify="flex-end"
      >
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

      </Flex>

      <Flex
        className={ styles.footer }
        gap="extra-small"
        justify="flex-end"
      >
        <Button
          onClick={ onClose }
          type="default"
        >
          { t('cancel') }
        </Button>
        <Button
          disabled={ selectedRowId === null }
          onClick={ () => {
            if (selectedRowId !== null) {
              // Pre-seed the key-name cache from the row we already have, so the
              // selected key's label shows immediately instead of after a refetch.
              const selectedRow = rows.find((row) => row.id === selectedRowId)
              if (selectedRow !== undefined) {
                void dispatch(
                  api.util.upsertQueryData(
                    'bundleDataImporterClassificationstoreLoadKeyName',
                    { keyId: selectedRowId },
                    {
                      keyId: selectedRowId,
                      groupName: selectedRow.groupName,
                      keyName: selectedRow.keyName
                    }
                  )
                )
              }
              onSelect(selectedRowId)
            }
          } }
          type="primary"
        >
          { t('common.apply-selection') }
        </Button>
      </Flex>
    </Modal>
  )
}
