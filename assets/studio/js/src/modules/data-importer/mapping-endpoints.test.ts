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
  useBundleDataImporterMappingLoadTransformationResultQuery,
  useBundleDataImporterMappingSuggestionLoadAiQuery,
  useBundleDataImporterMappingSuggestionSuggestMutation
} from './data-importer-api-slice.gen'
import {
  api as enhancedApi,
  useBundleDataImporterMappingSuggestionLoadAiQuery as useEnhancedMappingSuggestionLoadAiQuery,
  useBundleDataImporterMappingSuggestionSuggestMutation as useEnhancedMappingSuggestionSuggestMutation
} from './data-importer-api-slice-enhanced'
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

  it('post the columns and the class for mapping suggestions', async () => {
    const parameters = { classId: 'Product', columns: [{ index: '0', label: 'Article number' }] }

    await store.dispatch(api.endpoints.bundleDataImporterMappingSuggestionSuggest.initiate({
      bundleDataImporterMappingSuggestionParameters: parameters
    }))

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/mapping-suggestions', method: 'POST', body: parameters }])
  })

  it('export the mapping suggestion mutation hook, plain and enhanced', () => {
    expect(useBundleDataImporterMappingSuggestionSuggestMutation).toBe(api.endpoints.bundleDataImporterMappingSuggestionSuggest.useMutation)
    expect(useEnhancedMappingSuggestionSuggestMutation).toBe(enhancedApi.endpoints.bundleDataImporterMappingSuggestionSuggest.useMutation)
  })

  it('read the AI stage of a suggestion request by its id', async () => {
    await store.dispatch(api.endpoints.bundleDataImporterMappingSuggestionLoadAi.initiate({ requestId: 'abc' }))

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/mapping-suggestions/ai/abc' }])
  })

  it('export the AI stage query hook, plain and enhanced', () => {
    expect(useBundleDataImporterMappingSuggestionLoadAiQuery).toBe(api.endpoints.bundleDataImporterMappingSuggestionLoadAi.useQuery)
    expect(useEnhancedMappingSuggestionLoadAiQuery).toBe(enhancedApi.endpoints.bundleDataImporterMappingSuggestionLoadAi.useQuery)
  })
})
