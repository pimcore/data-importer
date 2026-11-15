/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container } from '@pimcore/studio-ui-bundle'
import { registerChangeControlReviewSurface } from './register-review-surface'
import { ImportConfigReviewSurface } from './import-config-review-surface'

jest.mock('@pimcore/studio-ui-bundle', () => ({ container: { get: jest.fn() } }))
jest.mock('./import-config-review-surface', () => ({ ImportConfigReviewSurface: () => null }))

const get = container.get as unknown as jest.Mock

beforeEach(() => {
  jest.useFakeTimers()
  get.mockReset()
})

afterEach(() => {
  jest.useRealTimers()
})

describe('registerChangeControlReviewSurface', () => {
  it('registers the import configuration surface once every module has initialised', () => {
    const registry = { registerDynamicType: jest.fn() }
    get.mockReturnValue(registry)

    registerChangeControlReviewSurface()
    expect(get).not.toHaveBeenCalled()
    jest.runAllTimers()

    expect(get).toHaveBeenCalledWith('ChangeControl/Review/SurfaceRegistry')
    expect(registry.registerDynamicType).toHaveBeenCalledWith({
      id: 'data-importer-config',
      component: ImportConfigReviewSurface,
      layout: { width: 1440 }
    })
  })

  it('does nothing without Change Control', () => {
    get.mockImplementation(() => { throw new Error('No matching bindings found') })

    registerChangeControlReviewSurface()

    expect(() => { jest.runAllTimers() }).not.toThrow()
  })

  it('does nothing when the registry is bound to nothing', () => {
    get.mockReturnValue(undefined)

    registerChangeControlReviewSurface()

    expect(() => { jest.runAllTimers() }).not.toThrow()
    expect(get).toHaveBeenCalledTimes(1)
  })
})
