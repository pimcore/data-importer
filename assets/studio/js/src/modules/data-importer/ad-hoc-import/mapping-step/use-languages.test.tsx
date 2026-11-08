/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { renderHook } from '@testing-library/react'
import { useLanguages } from './use-languages'

let mockValidLanguages: string[] | undefined
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: mockValidLanguages }) }))

describe('the languages of localized fields', () => {
  it('are the valid languages of the system', () => {
    mockValidLanguages = ['en', 'de']

    expect(renderHook(() => useLanguages()).result.current).toEqual(['en', 'de'])
  })

  it('are none while the system has none', () => {
    mockValidLanguages = undefined

    expect(renderHook(() => useLanguages()).result.current).toEqual([])
  })
})
