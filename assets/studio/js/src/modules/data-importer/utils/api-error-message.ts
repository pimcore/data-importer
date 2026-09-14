/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

const text = (value: unknown): string | undefined =>
  typeof value === 'string' && value.trim() !== '' ? value : undefined

/**
 * What the server actually said, out of a failed RTK Query result.
 *
 * Studio's ApiError reads `message`, `error` or `errorKey`; a Symfony HTTP exception
 * serialises as problem+json and puts its sentence in `detail` instead, so everything the
 * importer throws — a missing source asset, an unreadable file — reaches the user as
 * "Something went wrong". This finds the sentence wherever it landed.
 */
export function apiErrorMessage (error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null) return undefined

  const data = (error as { data?: unknown }).data
  if (typeof data !== 'object' || data === null) return text((error as { error?: unknown }).error)

  const body = data as Record<string, unknown>

  return text(body.message) ?? text(body.detail) ?? text(body.error)
}

/**
 * The same failure with its sentence where ApiError looks for it. Pass the result to
 * `trackError`, which surfaces it; never pair it with a toast of your own.
 */
export function withApiMessage (error: unknown): Record<string, unknown> {
  const message = apiErrorMessage(error)
  const base = typeof error === 'object' && error !== null ? { ...error } : {}

  return message === undefined ? base : { ...base, message }
}
