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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Flex, Text } from '@pimcore/studio-ui-bundle/components'
import { type ClassAttribute, type MappingConfigItem } from '../../../../../types'
import { useStyles } from './step-target.styles'
import { StepTargetPreviewActions } from './step-target-preview-actions'
import { StepTargetFields } from './step-target-fields'
import { useClassAttributes } from './useClassAttributes'

export interface StepTargetProps {
  attributesMap: Record<string, ClassAttribute[]>
  transformationResultType?: string
  dataTarget?: MappingConfigItem['dataTarget']
  classId?: string
  onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void
}

export const StepTarget = (props: StepTargetProps): React.JSX.Element => {
  const { classId, transformationResultType, dataTarget, onDataTargetChange } = props
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { classFieldOptions, isLocalized } = useClassAttributes(props)

  return (
    <Flex
      className={ styles.twoColumnLayout }
      gap="extra-small"
    >
      <Flex
        className={ styles.leftColumn }
        vertical
      >
        <Flex
          align="center"
          className={ styles.leftHeader }
        >
          <Text
            className={ styles.leftHeaderTitle }
            strong
          >
            {t('data-importer.mapping.advanced-modal.step-target')}
          </Text>
        </Flex>

        <StepTargetFields
          classFieldOptions={ classFieldOptions }
          classId={ classId }
          dataTarget={ dataTarget }
          isLocalized={ isLocalized }
          onDataTargetChange={ onDataTargetChange }
          transformationResultType={ transformationResultType }
        />
      </Flex>

      <StepTargetPreviewActions />
    </Flex>
  )
}
