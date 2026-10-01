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
import { afterEach, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen } from '@testing-library/react'

const getQuery = vi.fn()
const headersQuery = vi.fn()
const previewQuery = vi.fn()
const typeQuery = vi.fn()
const resultQuery = vi.fn()

vi.mock('../src/modules/data-importer/data-importer-api-slice-enhanced', () => ({
  useBundleDataImporterConfigGetQuery: (...args: unknown[]) => getQuery(...args)
}))

vi.mock('../src/modules/data-importer/data-importer-api-slice.gen', () => ({
  useBundleDataImporterConfigLoadColumnHeadersQuery: (...args: unknown[]) => headersQuery(...args),
  useBundleDataImporterConfigLoadPreviewQuery: (...args: unknown[]) => previewQuery(...args),
  useBundleDataImporterConfigCalculateTransformationResultTypeQuery: (...args: unknown[]) => typeQuery(...args),
  useBundleDataImporterConfigLoadTransformationResultQuery: (...args: unknown[]) => resultQuery(...args)
}))

// the data setup tab's own steps are out of scope here; the mapping step stand-in reports its source
vi.mock('../src/modules/data-importer/components/tabs/steps/mapping-step', async () => {
  const { useMappingSource } = await import('../src/modules/data-importer/components/tabs/steps/mapping-source/mapping-source')
  return {
    MappingStep: () => {
      const source = useMappingSource()
      return <span data-testid="mapping-source">{ `${source.id}:${String(source.revision)}` }</span>
    }
  }
})
vi.mock('../src/modules/data-importer/components/tabs/steps/data-source-step', () => ({ DataSourceStep: () => null }))
vi.mock('../src/modules/data-importer/components/tabs/steps/preview-import-step', () => ({ PreviewImportStep: () => null }))
vi.mock('../src/modules/data-importer/components/tabs/steps/resolver-step', () => ({ ResolverStep: () => null }))
vi.mock('../src/modules/data-importer/components/tabs/steps/processing-settings-step', () => ({ ProcessingSettingsStep: () => null }))
vi.mock('../src/modules/data-importer/hooks/use-column-header-options', () => ({ useColumnHeaderOptions: () => [] }))
vi.mock('../src/modules/data-importer/components/tabs/data-setup-tab.styles', () => ({ useStyles: () => ({ styles: {} }) }))
vi.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
vi.mock('@pimcore/studio-ui-bundle/components', () => ({
  Steps: () => null,
  Box: ({ children }: { children: React.ReactNode }) => <>{ children }</>,
  Flex: ({ children }: { children: React.ReactNode }) => <>{ children }</>
}))

const { storedConfigQueries } = await import('../src/modules/data-importer/components/tabs/steps/mapping-source/stored-config-mapping-source')
const { DataSetupTab } = await import('../src/modules/data-importer/components/tabs/data-setup-tab')

afterEach(() => {
  cleanup()
  vi.clearAllMocks()
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
})
