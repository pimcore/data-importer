/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { apiErrorMessage, withApiMessage } from './api-error-message'

describe('apiErrorMessage', () => {
  it('finds the sentence wherever the server put it', () => {
    expect(apiErrorMessage({ status: 400, data: { message: 'From message' } })).toBe('From message')
    expect(apiErrorMessage({ status: 404, data: { detail: 'Asset /feeds/cars.csv not found' } }))
      .toBe('Asset /feeds/cars.csv not found')
    expect(apiErrorMessage({ status: 500, data: { error: 'From error' } })).toBe('From error')
  })

  it('prefers the message, and skips blank sentences', () => {
    expect(apiErrorMessage({ data: { message: 'First', detail: 'Second' } })).toBe('First')
    expect(apiErrorMessage({ data: { message: '  ', detail: 'Second' } })).toBe('Second')
  })

  it('reads a fetch failure that carries no body', () => {
    expect(apiErrorMessage({ status: 'FETCH_ERROR', error: 'TypeError: Failed to fetch' }))
      .toBe('TypeError: Failed to fetch')
  })

  it('has nothing to say about anything else', () => {
    expect(apiErrorMessage(undefined)).toBeUndefined()
    expect(apiErrorMessage('boom')).toBeUndefined()
    expect(apiErrorMessage({ data: {} })).toBeUndefined()
  })
})

describe('withApiMessage', () => {
  it('puts the sentence where Studio\'s error display looks for it', () => {
    expect(withApiMessage({ status: 404, data: { detail: 'Not found' } }))
      .toEqual({ status: 404, data: { detail: 'Not found' }, message: 'Not found' })
  })

  it('leaves a failure without a sentence as it is', () => {
    expect(withApiMessage({ status: 500, data: {} })).toEqual({ status: 500, data: {} })
    expect(withApiMessage(null)).toEqual({})
  })
})
