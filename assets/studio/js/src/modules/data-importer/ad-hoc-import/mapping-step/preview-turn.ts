/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// each request reads the whole file on the server, so a long mapping asks for a few columns at a time
const PREVIEW_CONCURRENCY = 4

let previewsRunning = 0
const previewsWaiting: Array<() => void> = []

const previewTurn = async (): Promise<void> => {
  if (previewsRunning < PREVIEW_CONCURRENCY) {
    previewsRunning += 1
    return
  }
  // a finished request hands its turn on, so none starts in between
  await new Promise<void>((resolve) => { previewsWaiting.push(resolve) })
}

const previewDone = (): void => {
  const next = previewsWaiting.shift()
  if (next === undefined) {
    previewsRunning -= 1
    return
  }
  next()
}

// runs a preview request once fewer than the limit run; one no longer wanted by then is not sent
export const inPreviewTurn = async <T, >(wanted: () => boolean, request: () => PromiseLike<T>): Promise<T | null> => {
  await previewTurn()
  try {
    return wanted() ? await request() : null
  } finally {
    previewDone()
  }
}
