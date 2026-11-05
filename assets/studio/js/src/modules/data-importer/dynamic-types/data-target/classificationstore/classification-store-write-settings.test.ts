/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import {
  WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED,
  defaultWriteSettings,
  resolveWriteIfSourceIsEmpty,
  withWriteIfSourceIsEmpty
} from './classification-store-write-settings'

describe('resolveWriteIfSourceIsEmpty', () => {
  it('shows the effective legacy value while the marker is absent, not the stored one', () => {
    // A batch entry carrying writeIfSourceIsEmpty: true is in fact dropping empty values, so
    // showing the stored value would state the opposite of what the import does.
    const stored = { fieldName: 'csField', writeIfSourceIsEmpty: true }

    expect(resolveWriteIfSourceIsEmpty(stored, 'classificationstoreBatch')).toBe(false)
    expect(resolveWriteIfSourceIsEmpty(stored, 'classificationstore')).toBe(true)
  })

  it('shows the effective legacy value for an entry with no stored value either', () => {
    expect(resolveWriteIfSourceIsEmpty({ fieldName: 'csField' }, 'classificationstoreBatch')).toBe(false)
    expect(resolveWriteIfSourceIsEmpty({ fieldName: 'csField' }, 'classificationstore')).toBe(true)
  })

  it('shows the stored value once the marker is present', () => {
    const marked = { [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true, writeIfSourceIsEmpty: false }

    expect(resolveWriteIfSourceIsEmpty(marked, 'classificationstoreBatch')).toBe(false)
    expect(resolveWriteIfSourceIsEmpty(marked, 'classificationstore')).toBe(false)
  })

  it('defaults a marked entry without a stored value to writing empty values', () => {
    const marked = { [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true }

    expect(resolveWriteIfSourceIsEmpty(marked, 'classificationstoreBatch')).toBe(true)
    expect(resolveWriteIfSourceIsEmpty(marked, 'classificationstore')).toBe(true)
  })
})

describe('withWriteIfSourceIsEmpty', () => {
  it('writes the marker alongside the new value, so the control takes effect on an old entry', () => {
    expect(withWriteIfSourceIsEmpty({ fieldName: 'csField' }, true)).toEqual({
      fieldName: 'csField',
      [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true,
      writeIfSourceIsEmpty: true
    })
  })

  it('introduces no second option name', () => {
    const settings = withWriteIfSourceIsEmpty({ fieldName: 'csField' }, false)

    expect(Object.keys(settings ?? {}).sort()).toEqual([
      WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED,
      'fieldName',
      'writeIfSourceIsEmpty'
    ].sort())
  })
})

describe('defaultWriteSettings', () => {
  // What `getDefaultSettings()` of both classification store dynamic types spreads on a
  // target-type change, so selecting either target also puts the entry under the control.
  it('keeps the effective legacy value of the target it switches to', () => {
    expect(defaultWriteSettings({}, 'classificationstoreBatch')).toEqual({
      [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true,
      writeIfSourceIsEmpty: false
    })

    expect(defaultWriteSettings({}, 'classificationstore')).toEqual({
      [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true,
      writeIfSourceIsEmpty: true
    })
  })

  it('keeps a value already decided for the entry', () => {
    const marked = { [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true, writeIfSourceIsEmpty: false }

    expect(defaultWriteSettings(marked, 'classificationstore')?.writeIfSourceIsEmpty).toBe(false)
  })
})
