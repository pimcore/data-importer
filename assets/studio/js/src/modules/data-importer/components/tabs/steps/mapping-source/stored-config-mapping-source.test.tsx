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
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { storedConfigQueries } from './stored-config-mapping-source'
import { DataSetupTab } from '../../data-setup-tab'
import type * as MappingSourceModule from './mapping-source'

const getQuery = jest.fn()
const headersQuery = jest.fn()
const previewQuery = jest.fn()
const typeQuery = jest.fn()
const resultQuery = jest.fn()
const columnHeaderOptions = jest.fn((..._args: unknown[]) => [])

jest.mock('../../../../data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterConfigGetQuery: (...args: unknown[]) => getQuery(...args)
}))

jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterConfigLoadColumnHeadersQuery: (...args: unknown[]) => headersQuery(...args),
  useBundleDataImporterConfigLoadPreviewQuery: (...args: unknown[]) => previewQuery(...args),
  useBundleDataImporterConfigCalculateTransformationResultTypeQuery: (...args: unknown[]) => typeQuery(...args),
  useBundleDataImporterConfigLoadTransformationResultQuery: (...args: unknown[]) => resultQuery(...args)
}))

// the data setup tab's own steps are out of scope here; the mapping step stand-in reports its source
jest.mock('../mapping-step', () => {
  const { useMappingSource } = jest.requireActual<typeof MappingSourceModule>('./mapping-source')
  return {
    MappingStep: () => {
      const source = useMappingSource()
      return <span data-testid="mapping-source">{ `${source.id}:${String(source.revision)}` }</span>
    }
  }
})
jest.mock('../data-source-step', () => ({ DataSourceStep: () => null }))
jest.mock('../preview-import-step', () => ({
  PreviewImportStep: ({ onPreviewDataChange }: { onPreviewDataChange: () => void }) => (
    <button
      onClick={ onPreviewDataChange }
      type="button"
    >
      preview data changed
    </button>
  )
}))
jest.mock('../resolver-step', () => ({ ResolverStep: () => null }))
jest.mock('../processing-settings-step', () => ({ ProcessingSettingsStep: () => null }))
jest.mock('../../../../hooks/use-column-header-options', () => ({ useColumnHeaderOptions: (...args: unknown[]) => columnHeaderOptions(...args) }))
jest.mock('../../data-setup-tab.styles', () => ({ useStyles: () => ({ styles: {} }) }))
jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Steps: () => null,
  Box: ({ children }: { children: React.ReactNode }) => <>{ children }</>,
  Flex: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
})

describe('storedConfigQueries', () => {
  it('binds every query to the configuration name', () => {
    const queries = storedConfigQueries('products')

    queries.useColumnHeadersQuery({ currentConfig: {} })
    queries.usePreviewQuery({ recordNumber: 2 }, { refetchOnMountOrArgChange: false })
    queries.useTransformationResultTypeQuery({ currentConfig: { dataSourceIndex: ['0'] } })
    queries.useTransformationResultQuery(undefined)

    expect(headersQuery).toHaveBeenCalledWith({ name: 'products', bundleDataImporterCopyPreviewParameters: { currentConfig: {} } }, { skip: false })
    expect(previewQuery).toHaveBeenCalledWith(
      { name: 'products', bundleDataImporterLoadPreviewParameters: { recordNumber: 2 } },
      { refetchOnMountOrArgChange: false, skip: false }
    )
    expect(typeQuery).toHaveBeenCalledWith(
      { name: 'products', bundleDataImporterCalculateTransformationResultTypeParameters: { currentConfig: { dataSourceIndex: ['0'] } } },
      { skip: false }
    )
    expect(resultQuery).toHaveBeenCalledWith({ name: 'products', bundleDataImporterLoadPreviewParameters: {} }, { skip: true })
  })
})

describe('DataSetupTab', () => {
  it('hands the mapping step the stored configuration as its source', () => {
    getQuery.mockReturnValue({ data: { configuration: { general: {} } }, isSuccess: true, requestId: 'r1' })

    render(<DataSetupTab configName="products" />)

    expect(getQuery).toHaveBeenCalledWith({ name: 'products' })
    expect(screen.getByTestId('mapping-source').textContent).toBe('products:r1')
  })

  it('reloads the column lists once the preview data changed', () => {
    getQuery.mockReturnValue({ data: { configuration: { general: {} } }, isSuccess: true, requestId: 'r1' })
    render(<DataSetupTab configName="products" />)
    expect(columnHeaderOptions).toHaveBeenLastCalledWith('products', false, 0)

    fireEvent.click(screen.getByRole('button', { name: 'preview data changed' }))

    expect(columnHeaderOptions).toHaveBeenLastCalledWith('products', false, 1)
  })
})
