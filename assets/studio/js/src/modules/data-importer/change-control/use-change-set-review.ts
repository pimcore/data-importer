/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect, useState } from 'react'

const CHANGE_SET_API = '/pimcore-studio/api/bundle/change-control/change-sets'

interface ReviewState {
  data?: unknown
  isLoading: boolean
  error?: unknown
}

/**
 * Reads a change set's review payload.
 *
 * Fetched directly rather than through Change Control's own RTK endpoints: those live behind
 * its federation remote, and this bundle must build and run without it. Swap this for the
 * shared hook once the review contract ships as a consumable package.
 */
export function useChangeSetReview (changeSetId?: string, contextRef?: string): ReviewState {
  const [state, setState] = useState<ReviewState>({ isLoading: true })

  useEffect(() => {
    if (changeSetId === undefined || changeSetId === '') {
      setState({ isLoading: false, error: new Error('no change set') })
      return
    }

    let cancelled = false
    const query = contextRef !== undefined && contextRef !== '' ? `?contextRef=${encodeURIComponent(contextRef)}` : ''

    setState({ isLoading: true })
    fetch(`${CHANGE_SET_API}/${encodeURIComponent(changeSetId)}/review${query}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(async (response) => {
        if (!response.ok) throw new Error(`review request failed: ${response.status}`)
        return await response.json()
      })
      .then((data) => { if (!cancelled) setState({ data, isLoading: false }) })
      .catch((error) => { if (!cancelled) setState({ isLoading: false, error }) })

    return () => { cancelled = true }
  }, [changeSetId, contextRef])

  return state
}
