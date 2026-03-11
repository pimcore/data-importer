/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Form, type formInstanceType } from '@pimcore/studio-ui-bundle/components'
import { createMappingItemId, type MappingConfigItem } from '../../../../../types'

export function ensureMappingId (item: MappingConfigItem): MappingConfigItem {
  return {
    ...item,
    mappingId: item.mappingId ?? createMappingItemId()
  }
}

export function ensureMappingIdAtIndex (form: formInstanceType, index: number): string {
  const item = form.getFieldValue(['mappingConfig', index]) as MappingConfigItem | undefined
  const existingId = item?.mappingId

  if (existingId !== undefined && existingId !== '') {
    return existingId
  }

  const generatedId = createMappingItemId()
  form.setFieldValue(['mappingConfig', index, 'mappingId'], generatedId, { triggerChange: false })
  return generatedId
}

export function findMappingIndexById (form: formInstanceType, mappingId: string): number {
  const currentItems = (form.getFieldValue('mappingConfig') as MappingConfigItem[] | undefined) ?? []
  return currentItems.findIndex((item) => item.mappingId === mappingId)
}
