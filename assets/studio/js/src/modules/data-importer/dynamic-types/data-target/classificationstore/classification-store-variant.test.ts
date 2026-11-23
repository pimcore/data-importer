/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { keyReferenceOfRow, splitKeyReference, withVariant } from './classification-store-variant'

describe('classification store key reference', () => {
  it('hands the identifier reference of a key to the mapping entry when it has one', () => {
    expect(keyReferenceOfRow({ id: '1-5', identifier: 'dimensions:width' })).toBe('dimensions:width')
  })

  it('hands the integer pair over when the key has no identifier', () => {
    expect(keyReferenceOfRow({ id: '1-5', identifier: null })).toBe('1-5')
    expect(keyReferenceOfRow({ id: '1-5' })).toBe('1-5')
  })

  it('splits a variant off the reference with the # separator', () => {
    expect(splitKeyReference('grp:key#metric')).toEqual({ reference: 'grp:key', variant: 'metric' })
    expect(splitKeyReference('grp:key')).toEqual({ reference: 'grp:key', variant: undefined })
    expect(splitKeyReference(undefined)).toEqual({ reference: '', variant: undefined })
  })

  it('replaces the variant of a reference and drops it for the default variant', () => {
    expect(withVariant('grp:key#metric', 'imperial')).toBe('grp:key#imperial')
    expect(withVariant('grp:key#metric', '')).toBe('grp:key')
    expect(withVariant('grp:key', undefined)).toBe('grp:key')
  })
})
