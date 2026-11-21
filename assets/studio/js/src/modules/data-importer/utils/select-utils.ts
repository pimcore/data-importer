/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/**
 * filterOption for Select components — matches against the option label (case-insensitive),
 * ignoring the raw option value.
 */
export const filterByLabel = (input: string, option?: { label?: unknown }): boolean => {
  return String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
}
