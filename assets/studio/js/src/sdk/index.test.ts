/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import * as sdk from './index'

// the Studio SDK is a runtime remote; its npm package ships types only
jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  injectable: () => (target: unknown) => target
}), { virtual: true })

jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  DynamicTypeAbstract: jest.fn(),
  DynamicTypeRegistryAbstract: jest.fn()
}), { virtual: true })

describe('SDK index', () => {
  it('exports the five registries', () => {
    expect(sdk.DynamicTypeInterpreterRegistry).toBeDefined()
    expect(sdk.DynamicTypeLoaderRegistry).toBeDefined()
    expect(sdk.DynamicTypeResolverRegistry).toBeDefined()
    expect(sdk.DynamicTypeTransformerRegistry).toBeDefined()
    expect(sdk.DynamicTypeDataTargetRegistry).toBeDefined()
  })

  it('exports the five abstract classes', () => {
    expect(sdk.DynamicTypeInterpreterAbstract).toBeDefined()
    expect(sdk.DynamicTypeLoaderAbstract).toBeDefined()
    expect(sdk.DynamicTypeResolverAbstract).toBeDefined()
    expect(sdk.DynamicTypeTransformerAbstract).toBeDefined()
    expect(sdk.DynamicTypeDataTargetAbstract).toBeDefined()
  })

  it('exports the registry service ids', () => {
    expect(sdk.bundleServiceIds).toMatchObject({
      'DataImporter/DynamicTypes/Interpreter/Registry': 'DataImporter/DynamicTypes/Interpreter/Registry',
      'DataImporter/DynamicTypes/Loader/Registry': 'DataImporter/DynamicTypes/Loader/Registry',
      'DataImporter/DynamicTypes/Resolver/Registry': 'DataImporter/DynamicTypes/Resolver/Registry',
      'DataImporter/DynamicTypes/Transformer/Registry': 'DataImporter/DynamicTypes/Transformer/Registry',
      'DataImporter/DynamicTypes/DataTarget/Registry': 'DataImporter/DynamicTypes/DataTarget/Registry'
    })
  })
})
