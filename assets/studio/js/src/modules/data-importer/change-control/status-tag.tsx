/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { Tag } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export type ChangeStatus = 'added' | 'changed' | 'removed' | 'moved' | 'whole'

// the same four words in the same four colours Studio's own form annotation uses, so a
// reader sees one vocabulary whether they look at the rail, a row header or a field
const STATUS_COLOUR: Record<ChangeStatus, string> = {
  added: 'green',
  changed: 'gold',
  removed: 'red',
  moved: 'geekblue',
  whole: 'default'
}

export const StatusTag: React.FC<{ readonly status: ChangeStatus }> = ({ status }) => {
  const { t } = useTranslation()

  return <Tag color={ STATUS_COLOUR[status] }>{ t(`data-importer.review.status.${status}`) }</Tag>
}
