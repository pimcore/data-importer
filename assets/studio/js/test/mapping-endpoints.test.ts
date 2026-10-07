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
import { beforeEach, describe, expect, it, vi } from 'vitest'

const sent = vi.hoisted(() => [] as unknown[])

// Studio's base api, with a base query that records the request instead of sending it
vi.mock('@pimcore/studio-ui-bundle/api', async () => {
  const { createApi } = await import('@reduxjs/toolkit/query/react')

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

const { api } = await import('../src/modules/data-importer/data-importer-api-slice.gen')

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
})
