/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// why a mapping is in its warning state: it has a source but no destination yet
export type MappingWarning = 'advanced-setup' | 'further-configuration'

export const mappingWarningOf = (dataSourceIndex: string[] | undefined, selectedFieldName: string | undefined): MappingWarning | null => {
  const sourceCount = (dataSourceIndex ?? []).length
  const hasDestination = selectedFieldName !== undefined && selectedFieldName !== ''

  if (sourceCount === 0 || hasDestination) return null

  // several sources only map through the advanced setup
  return sourceCount > 1 ? 'advanced-setup' : 'further-configuration'
}
