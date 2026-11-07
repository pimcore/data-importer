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
import * as enhanced from '../data-importer-api-slice-enhanced'
import * as generated from '../data-importer-api-slice.gen'
import type * as RtkQuery from '@reduxjs/toolkit/query/react'

const { api } = enhanced

const FILE_URL = '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file'

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

describe('the ad hoc import endpoints', () => {
  it('post the file', async () => {
    const body = { file: new Blob(['sku\n']), folderId: 5 }

    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportUploadFile.initiate({ body }))

    expect(sent).toEqual([{ url: FILE_URL, method: 'POST', body }])
  })

  it('post the settings to inspect a file by', async () => {
    const settings = { hasHeaderRow: false }

    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportInspectFile.initiate({
      fileId: 'id-1',
      bundleDataImporterAdHocImportFileSettings: settings
    }))

    expect(sent).toEqual([{ url: `${FILE_URL}/id-1/inspect`, method: 'POST', body: settings }])
  })

  it('delete a file by its id', async () => {
    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportDeleteFile.initiate({ fileId: 'id-1' }))

    expect(sent).toEqual([{ url: `${FILE_URL}/id-1`, method: 'DELETE' }])
  })

  it('ask for the fields to match by per class', async () => {
    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportLoadMatchFields.initiate({ classId: 'CAR' }))

    expect(sent).toEqual([{
      url: '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/match-fields',
      params: { classId: 'CAR' }
    }])
  })

  it('post the columns to load the mapping of a class for', async () => {
    const parameters = { classId: 'CAR', columns: [{ index: 'Name DE', label: 'Name DE' }] }

    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportLoadMapping.initiate({
      bundleDataImporterMappingSuggestionParameters: parameters
    }))

    expect(sent).toEqual([{
      url: '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/mapping',
      method: 'POST',
      body: parameters
    }])
  })

  it('get the configurations to map with', async () => {
    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportListConfigurations.initiate())

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/configurations' }])
  })

  it('get the configurations again once a configuration is saved', async () => {
    const listed = store.dispatch(api.endpoints.bundleDataImporterAdHocImportListConfigurations.initiate())
    await listed
    sent.length = 0

    store.dispatch(api.util.invalidateTags(['DataHubConfigs']))
    for (let tick = 0; tick < 10 && sent.length === 0; tick++) {
      await new Promise((resolve) => setTimeout(resolve, 0))
    }
    listed.unsubscribe()

    expect(sent).toEqual([{ url: '/pimcore-studio/api/bundle/data-importer/ad-hoc-import/configurations' }])
  })

  it('post a column\'s transformations with the file\'s settings to the file', async () => {
    const parameters = {
      classId: 'CAR',
      column: 'Images',
      field: 'gallery',
      transformationPipeline: [{ type: 'explode', settings: { delimiter: ',' } }],
      delimiter: ';',
      hasHeaderRow: true
    }

    await store.dispatch(api.endpoints.bundleDataImporterAdHocImportPreviewTransformations.initiate({
      fileId: 'id-1',
      bundleDataImporterAdHocImportTransformationParameters: parameters
    }))

    expect(sent).toEqual([{
      url: `${FILE_URL}/id-1/transformations`,
      method: 'POST',
      body: parameters
    }])
  })

  it('are exported as hooks', () => {
    const inspect = api.endpoints.bundleDataImporterAdHocImportInspectFile.useMutation
    const remove = api.endpoints.bundleDataImporterAdHocImportDeleteFile.useMutation
    const matchFields = api.endpoints.bundleDataImporterAdHocImportLoadMatchFields.useQuery
    const mapping = api.endpoints.bundleDataImporterAdHocImportLoadMapping.useMutation
    const configurations = api.endpoints.bundleDataImporterAdHocImportListConfigurations.useQuery
    const transformations = api.endpoints.bundleDataImporterAdHocImportPreviewTransformations.useMutation

    expect(generated.useBundleDataImporterAdHocImportUploadFileMutation).toBe(api.endpoints.bundleDataImporterAdHocImportUploadFile.useMutation)
    expect(enhanced.useBundleDataImporterConnectionListQuery).toBe(api.endpoints.bundleDataImporterConnectionList.useQuery)
    for (const hooks of [generated, enhanced]) {
      expect(hooks.useBundleDataImporterAdHocImportInspectFileMutation).toBe(inspect)
      expect(hooks.useBundleDataImporterAdHocImportDeleteFileMutation).toBe(remove)
      expect(hooks.useBundleDataImporterAdHocImportLoadMatchFieldsQuery).toBe(matchFields)
      expect(hooks.useBundleDataImporterAdHocImportLoadMappingMutation).toBe(mapping)
      expect(hooks.useBundleDataImporterAdHocImportListConfigurationsQuery).toBe(configurations)
      expect(hooks.useBundleDataImporterAdHocImportPreviewTransformationsMutation).toBe(transformations)
    }
  })
})
