/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { ConfigProvider } from 'antd'
import { fireEvent, render, screen } from '@testing-library/react'
import { PreviewPanel } from './preview-panel'

const placeholder = 'data-importer.mapping.advanced-modal.step-source.search-placeholder'
// the query hook hands out the same rows while its request is unchanged; the panel's effects rely on that
const mockRows = [{ dataIndex: 'sku', label: 'SKU', data: 'A-1' }, { dataIndex: 'name', label: 'Name', data: 'Chair' }]

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('../../shared/use-preview-record-query', () => ({
  usePreviewRecordQuery: () => ({
    dataPreview: mockRows,
    currentRecordIndex: 0,
    isLoading: false,
    isFetching: false,
    isError: false,
    load: jest.fn()
  })
}))
jest.mock('../../mapping-source/mapping-source', () => ({
  useMappingSource: () => ({
    useTransformationResultQuery: () => ({ isLoading: false, isFetching: false, isError: false, refetch: jest.fn() })
  })
}))

describe('PreviewPanel', () => {
  it('keeps the import preview searchable inside a disabled dialog', () => {
    render(
      <ConfigProvider componentDisabled>
        <PreviewPanel
          mode="import"
          selectedDataSourceIndex={ ['sku'] }
        />
      </ConfigProvider>
    )

    const search = screen.getByPlaceholderText<HTMLInputElement>(placeholder)
    expect(search.disabled).toBe(false)

    fireEvent.change(search, { target: { value: 'chair' } })
    expect(screen.queryByText('A-1')).toBeNull()
    expect(screen.queryByText('Chair')).not.toBeNull()
  })
})
