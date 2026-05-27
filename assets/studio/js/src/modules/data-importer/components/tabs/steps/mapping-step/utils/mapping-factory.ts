/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { uuid } from '@pimcore/studio-ui-bundle/utils'
import type { MappingConfigItem } from '../../../../../types'

export type MappingCreateMode = 'manual' | 'autofill'

export function createMappingItem (
  dataIndex: string,
  label: string,
  mode: MappingCreateMode = 'manual',
  targetFieldName?: string,
  language?: string
): MappingConfigItem {
  const resolvedFieldName = targetFieldName ?? dataIndex
  return {
    mappingId: uuid(),
    label,
    dataSourceIndex: [dataIndex],
    transformationResultType: mode === 'autofill' ? 'default' : undefined,
    dataTarget: {
      type: 'direct',
      settings: {
        ...(mode === 'autofill' && { fieldName: resolvedFieldName }),
        ...(language !== undefined && language !== '' && { language }),
        writeIfTargetIsNotEmpty: true,
        writeIfSourceIsEmpty: true
      }
    }
  }
}
