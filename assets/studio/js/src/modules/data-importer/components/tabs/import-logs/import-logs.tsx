/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useAppDispatch, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { invalidatingTags } from '@pimcore/studio-ui-bundle/api'
import {
  Content,
  ContentLayout,
  CreatableSelect,
  Divider,
  Flex,
  Icon,
  IconButton,
  Pagination,
  Sidebar,
  Toolbar
} from '@pimcore/studio-ui-bundle/components'
import {
  api,
  ApplicationLoggerTable,
  useFilter,
  useBundleApplicationLoggerGetCollectionQuery
} from '@pimcore/studio-ui-bundle/modules/application-logger'
import { useElementVisible } from '@pimcore/studio-ui-bundle/utils'
import { isNil } from 'lodash'
import React, { useCallback, useEffect, useRef, useState } from 'react'
import { FilterSidebar } from './filter-sidebar/filter-sidebar'
import { useRefreshInterval } from './hooks/use-refresh-interval/use-refresh-interval'
import { SidebarProvider } from './sidebar-provider/sidebar-provider'

const COMPONENT_PREFIX = 'DATA-IMPORTER '

export interface ImportLogsProps {
  configName: string
}

const sidebarEntries = [
  {
    key: 'filter',
    icon: (
      <Icon
        options={ { width: '16px', height: '16px' } }
        value='filter'
      />
    ),
    component: <FilterSidebar />
  }
]

export const ImportLogs = (props: ImportLogsProps): React.JSX.Element => {
  const { configName } = props
  const { t } = useTranslation()
  const dispatch = useAppDispatch()
  const [currentPage, setCurrentPage] = useState<number>(1)
  const [pageSize, setPageSize] = useState<number>(20)

  const { columnFilters, setIsLoading: setFilterLoading } = useFilter()

  // Always append the fixed component filter silently
  const mergedFilters = [
    ...columnFilters,
    {
      key: 'component',
      type: 'equals',
      filterValue: COMPONENT_PREFIX + configName
    }
  ]

  const { data, isFetching } = useBundleApplicationLoggerGetCollectionQuery({
    body: {
      filters: {
        page: currentPage,
        pageSize,
        columnFilters: mergedFilters,
        sortFilter: { key: 'id', direction: 'DESC' }
      }
    }
  })

  const total = data?.totalItems ?? 0

  const onPagerChange = (page: number, newPageSize: number): void => {
    setCurrentPage(page)
    setPageSize(newPageSize)
  }

  const refreshData = useCallback((): void => {
    dispatch(
      api.util.invalidateTags(
        invalidatingTags.APPLICATION_LOGGER()
      )
    )
  }, [dispatch])

  const { refreshInterval, setRefreshInterval } = useRefreshInterval(refreshData)

  useEffect(() => {
    setFilterLoading(isFetching)
  }, [isFetching])

  const wrapperRef = useRef<HTMLDivElement>(null)
  const isVisible = useElementVisible(wrapperRef, true)
  const skipInitialVisibilityRef = useRef(true)

  useEffect(() => {
    if (!isVisible) return
    if (skipInitialVisibilityRef.current) {
      skipInitialVisibilityRef.current = false
      return
    }
    refreshData()
  }, [isVisible, refreshData])

  return (
    <SidebarProvider>
      <div
        ref={ wrapperRef }
        style={ { height: '100%' } }
      >
        <ContentLayout
          className='h-full'
          renderSidebar={ <Sidebar entries={ sidebarEntries } /> }
          renderToolbar={
            <Toolbar
              justify='space-between'
              theme='secondary'
            >
              <Flex
                align="center"
                gap={ 8 }
              >
                {!isNil(refreshInterval) && (
                  <span>{t('application-logger.refresh-interval')}</span>
                )}
                <CreatableSelect
                  allowClear
                  inputType='number'
                  minWidth={ 200 }
                  numberInputProps={ {
                    min: 1
                  } }
                  onChange={ setRefreshInterval }
                  onCreateOption={ (value) => {
                    return {
                      value,
                      label: t('application-logger.refresh-interval.seconds', { seconds: value })
                    }
                  } }
                  options={ [
                    { value: '3', label: t('application-logger.refresh-interval.seconds', { seconds: 3 }) },
                    { value: '5', label: t('application-logger.refresh-interval.seconds', { seconds: 5 }) },
                    { value: '10', label: t('application-logger.refresh-interval.seconds', { seconds: 10 }) },
                    { value: '30', label: t('application-logger.refresh-interval.seconds', { seconds: 30 }) },
                    { value: '60', label: t('application-logger.refresh-interval.seconds', { seconds: 60 }) }
                  ] }
                  placeholder={ t('application-logger.refresh-interval.select') }
                  validate={ (value) => !Number.isNaN(Number.parseInt(value)) && Number.parseInt(value) > 0 }
                  value={ refreshInterval }
                />
              </Flex>

              <Flex>
                <IconButton
                  disabled={ isFetching }
                  icon={ { value: 'refresh' } }
                  onClick={ refreshData }
                />
                {total > 0 && (
                  <>
                    <Divider
                      size="small"
                      type="vertical"
                    />
                    <Pagination
                      current={ currentPage }
                      defaultPageSize={ pageSize }
                      onChange={ onPagerChange }
                      showSizeChanger
                      showTotal={ (total) => t('pagination.show-total', { total }) }
                      total={ total }
                    />
                  </>
                )}
              </Flex>
            </Toolbar>
          }
        >
          <Content
            loading={ isFetching }
            padded
          >
            <ApplicationLoggerTable items={ data?.items ?? [] } />
          </Content>
        </ContentLayout>
      </div>
    </SidebarProvider>
  )
}
