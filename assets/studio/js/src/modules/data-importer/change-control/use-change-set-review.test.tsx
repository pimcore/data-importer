/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { cleanup, renderHook, waitFor } from '@testing-library/react'
import { useChangeSetReview } from './use-change-set-review'

const originalFetch = globalThis.fetch
const fetchMock = jest.fn()

const respond = (ok: boolean, status: number, body: unknown): Response =>
  ({ ok, status, json: async () => await Promise.resolve(body) }) as unknown as Response

beforeEach(() => {
  fetchMock.mockReset()
  globalThis.fetch = fetchMock as unknown as typeof fetch
})

afterEach(() => {
  cleanup()
  globalThis.fetch = originalFetch
})

describe('useChangeSetReview', () => {
  it('reads the review of the change set in the context it is shown in', async () => {
    fetchMock.mockResolvedValue(respond(true, 200, { slots: {} }))

    const { result } = renderHook(() => useChangeSetReview('cs/1', 'agent-session:7'))

    await waitFor(() => { expect(result.current.isLoading).toBe(false) })
    expect(result.current).toEqual({ data: { slots: {} }, isLoading: false })
    expect(fetchMock).toHaveBeenCalledWith(
      '/pimcore-studio/api/bundle/change-control/change-sets/cs%2F1/review?contextRef=agent-session%3A7',
      { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
    )
  })

  it('asks without a context when none is given', async () => {
    fetchMock.mockResolvedValue(respond(true, 200, {}))

    const { result } = renderHook(() => useChangeSetReview('cs-1', ''))

    await waitFor(() => { expect(result.current.isLoading).toBe(false) })
    expect(fetchMock.mock.calls[0][0]).toBe('/pimcore-studio/api/bundle/change-control/change-sets/cs-1/review')
  })

  it('reports a failed request as an error', async () => {
    fetchMock.mockResolvedValue(respond(false, 403, {}))

    const { result } = renderHook(() => useChangeSetReview('cs-1'))

    await waitFor(() => { expect(result.current.isLoading).toBe(false) })
    expect(result.current.data).toBeUndefined()
    expect(String(result.current.error)).toBe('Error: review request failed: 403')
  })

  it('reports no change set without asking', () => {
    const { result } = renderHook(() => useChangeSetReview(undefined))

    expect(result.current.isLoading).toBe(false)
    expect(String(result.current.error)).toBe('Error: no change set')
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('drops an answer that arrives after the change set changed', async () => {
    let answerFirst: (response: Response) => void = () => {}
    fetchMock
      .mockReturnValueOnce(new Promise<Response>((resolve) => { answerFirst = resolve }))
      .mockResolvedValueOnce(respond(true, 200, { id: 'second' }))

    const { result, rerender } = renderHook(({ id }) => useChangeSetReview(id), { initialProps: { id: 'cs-1' } })
    rerender({ id: 'cs-2' })
    await waitFor(() => { expect(result.current.data).toEqual({ id: 'second' }) })

    answerFirst(respond(true, 200, { id: 'first' }))
    await Promise.resolve()

    expect(result.current.data).toEqual({ id: 'second' })
  })
})
