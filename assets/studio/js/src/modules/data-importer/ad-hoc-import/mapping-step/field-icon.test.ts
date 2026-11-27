/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { iconOf } from './field-icon'

describe('the glyph of a field', () => {
  it('draws each field kind with its own glyph', () => {
    expect(iconOf({ key: 'name', localized: false, classificationStore: false })).toBe('long-text')
    expect(iconOf({ key: 'description', localized: true, classificationStore: false })).toBe('world')
    expect(iconOf({ key: 'attributes.Engine.cylinders', localized: false, classificationStore: false })).toBe('data-object')
    expect(iconOf({ key: 'specs', localized: false, classificationStore: true })).toBe('menu')
  })

  it('draws a plain field when none is chosen', () => {
    expect(iconOf(undefined)).toBe('long-text')
  })
})
