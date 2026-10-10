/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */


import { DataImporterModule } from '.'
import { ResolverStep } from './components/tabs/steps/resolver-step'
import { AdvancedMappingModal } from './components/tabs/steps/advanced-mapping-modal'
import { PostedMappingSource } from './components/tabs/steps/mapping-source/posted-mapping-source'
import { transformBackendToForm, transformFormToBackend } from './utils/transformers'
import type * as RtkQuery from '@reduxjs/toolkit/query/react'

// the container records what the module binds; it is read back through the mocked module
jest.mock('@pimcore/studio-ui-bundle', () => {
  const bindings = new Map<string, unknown>()
  const registry = { registerDynamicType: () => undefined }

  return {
    bindings,
    container: {
      bind: (id: string) => ({
        toConstantValue: (value: unknown) => { bindings.set(id, value) },
        to: (type: unknown) => {
          bindings.set(id, type)
          return { inSingletonScope: () => undefined }
        }
      }),
      get: () => registry
    }
  }
})
jest.mock('@pimcore/studio-ui-bundle/app', () => ({ injectable: () => <T>(target: T): T => target }))
// the bases the bundle's dynamic types extend
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  DynamicTypeAbstract: class { id?: string },
  DynamicTypeRegistryAbstract: class { id?: string }
}))
jest.mock('@pimcore/data-hub', () => ({
  DynamicTypeDataHubAdapterAbstract: class { id?: string },
  bundleServiceIds: { 'DataHub/DynamicTypes/Adapter/Registry': 'DataHub/DynamicTypes/Adapter/Registry' }
}))
jest.mock('@pimcore/studio-ui-bundle/api', () => {
  const { createApi } = jest.requireActual<typeof RtkQuery>('@reduxjs/toolkit/query/react')
  return { api: createApi({ baseQuery: async () => await Promise.resolve({ data: {} }), endpoints: () => ({}) }) }
})

const { bindings } = jest.requireMock<{ bindings: Map<string, unknown> }>('@pimcore/studio-ui-bundle')

describe('DataImporterModule', () => {
  it('binds the resolver step, the mapping dialog, its source and the converters for other bundles', () => {
    DataImporterModule.onInit()

    expect(bindings.get('DataImporter/Components/ResolverStep')).toBe(ResolverStep)
    expect(bindings.get('DataImporter/Components/AdvancedMappingModal')).toBe(AdvancedMappingModal)
    expect(bindings.get('DataImporter/Components/PostedMappingSource')).toBe(PostedMappingSource)
    expect(bindings.get('DataImporter/Utils/TransformBackendToForm')).toBe(transformBackendToForm)
    expect(bindings.get('DataImporter/Utils/TransformFormToBackend')).toBe(transformFormToBackend)
  })
})
