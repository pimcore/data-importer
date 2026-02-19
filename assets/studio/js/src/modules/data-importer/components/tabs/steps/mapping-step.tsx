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
import { Panel } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export interface MappingStepProps {
  configName: string
}

export const MappingStep = ({ configName }: MappingStepProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <Panel title={ t('data-importer.data-setup.steps.mapping.title') }>
      {t('data-importer.data-setup.steps.mapping.placeholder')}
    </Panel>
  )
}
