/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { configureStore } from '@reduxjs/toolkit'
import {
  api,
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery,
  useBundleDataImporterMappingLoadTransformationResultQuery
} from './data-importer-api-slice.gen'
import type * as RtkQuery from '@reduxjs/toolkit/query/react'

const sent: unknown[] = []

// Studio's base api, with a base query that records the request instead of sending it
jest.mock('@pimcore/studio-ui-bundle/api', () => {
  const { createApi } = jest.requireActual<typeof RtkQuery>('@reduxjs/toolkit/query/react')

  return {
    api: createApi({
      baseQuery: async (args: unknown) => {
        sent.push(args)
        return await Promise.resolve({ data: {} })
      },
      endpoints: () => ({})
    })
  }
})

const store = configureStore({
  reducer: { [api.reducerPath]: api.reducer },
  middleware: (getDefaultMiddleware) => getDefaultMiddleware().concat(api.middleware)
})

beforeEach(() => {
  sent.length = 0
})

describe('the posted mapping endpoints', () => {
  it('post the mapping and the record for the transformation result previews', async () => {
    const parameters = { mappingConfig: [{ dataSourceIndex: ['sku'] }], dataRow: { sku: 'A-1' } }

    await store.dispatch(api.endpoints.bundleDataImporterMappingLoadTransformationResult.initiate({
      bundleDataImporterTransformationResultParameters: parameters
    }))

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/mapping/transformation-result', method: 'POST', body: parameters }])
  })

  it('post the mapping entry for its transformation result type', async () => {
    const parameters = { currentConfig: { dataSourceIndex: ['price'], transformationPipeline: [{ type: 'numeric' }] } }

    await store.dispatch(api.endpoints.bundleDataImporterMappingCalculateTransformationResultType.initiate({
      bundleDataImporterCalculateTransformationResultTypeParameters: parameters
    }))

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/mapping/transformation-result-type', method: 'POST', body: parameters }])
  })

  it('are exported as the hooks the posted mapping source uses', () => {
    expect(useBundleDataImporterMappingLoadTransformationResultQuery).toBe(api.endpoints.bundleDataImporterMappingLoadTransformationResult.useQuery)
    expect(useBundleDataImporterMappingCalculateTransformationResultTypeQuery).toBe(api.endpoints.bundleDataImporterMappingCalculateTransformationResultType.useQuery)
  })
})
