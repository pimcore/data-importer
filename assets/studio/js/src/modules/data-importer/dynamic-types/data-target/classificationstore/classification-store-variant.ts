/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

const VARIANT_SEPARATOR = '#'

/**
 * A classification store key reference split into the key and the variant it is spelled with:
 * `<GROUP_IDENTIFIER>:<KEY_IDENTIFIER>#<VARIANT>`, the variant being optional.
 */
export const splitKeyReference = (keyId: string | undefined): { reference: string, variant: string | undefined } => {
  const [reference, ...rest] = (keyId ?? '').split(VARIANT_SEPARATOR)
  const variant = rest.join(VARIANT_SEPARATOR)

  return { reference, variant: variant === '' ? undefined : variant }
}

/** The reference of the same key addressing the given variant, or the default variant when none is given. */
export const withVariant = (keyId: string, variant: string | undefined): string => {
  const { reference } = splitKeyReference(keyId)

  return variant === undefined || variant === '' ? reference : `${reference}${VARIANT_SEPARATOR}${variant}`
}

/** What a mapping entry stores for a key: the identifier reference when it has one, the integer pair otherwise. */
export const keyReferenceOfRow = (row: { id?: string, identifier?: string | null }): string =>
  row.identifier ?? row.id ?? ''
