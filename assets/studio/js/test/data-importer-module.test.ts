/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { describe, expect, it, vi } from 'vitest'

const { bindings, container } = vi.hoisted(() => {
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

vi.mock('@pimcore/studio-ui-bundle', () => ({ container }))
vi.mock('@pimcore/studio-ui-bundle/app', () => ({ injectable: () => <T>(target: T): T => target }))
// the bases the bundle's dynamic types extend
vi.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  DynamicTypeAbstract: class { id?: string },
  DynamicTypeRegistryAbstract: class { id?: string }
}))
vi.mock('@pimcore/data-hub', () => ({
  DynamicTypeDataHubAdapterAbstract: class { id?: string },
  bundleServiceIds: { 'DataHub/DynamicTypes/Adapter/Registry': 'DataHub/DynamicTypes/Adapter/Registry' }
}))
vi.mock('@pimcore/studio-ui-bundle/api', async () => {
  const { createApi } = await import('@reduxjs/toolkit/query/react')
  return { api: createApi({ baseQuery: async () => await Promise.resolve({ data: {} }), endpoints: () => ({}) }) }
})

const { DataImporterModule } = await import('../src/modules/data-importer')
const { ResolverStep } = await import('../src/modules/data-importer/components/tabs/steps/resolver-step')
const { AdvancedMappingModal } = await import('../src/modules/data-importer/components/tabs/steps/advanced-mapping-modal')
const { PostedMappingSource } = await import('../src/modules/data-importer/components/tabs/steps/mapping-source/posted-mapping-source')
const { transformBackendToForm, transformFormToBackend } = await import('../src/modules/data-importer/utils/transformers')

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
