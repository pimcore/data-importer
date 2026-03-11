/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useState } from 'react'
import { Steps, type StepItem, Flex } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataSourceStep } from './steps/data-source-step'
import { PreviewImportStep } from './steps/preview-import-step'
import { ResolverStep } from './steps/resolver-step'
import { MappingStep } from './steps/mapping-step'
import { ProcessingSettingsStep } from './steps/processing-settings-step'
import { useStyles } from './data-setup-tab.styles'
import { useBundleDataImporterConfigGetQuery } from '../../data-importer-api-slice-enhanced'
import { Box } from '@pimcore/studio-ui-bundle/components'

export interface DataSetupTabProps {
  configName: string
}

const MAPPING_STEP_INDEX = 3

export const DataSetupTab = ({ configName }: DataSetupTabProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const [currentStep, setCurrentStep] = useState(0)

  const { data: configData } = useBundleDataImporterConfigGetQuery({ name: configName })
  const columnHeaderOptions = (configData?.columnHeaders ?? []).map((header) => {
    // API returns objects {id, dataIndex, label}; type says string[] — handle both
    const h = header as unknown as { dataIndex?: string, label?: string } | string
    const value = typeof h === 'string' ? h : (h.dataIndex ?? '')
    const label = typeof h === 'string' ? h : (h.label ?? h.dataIndex ?? '')
    return { value, label }
  })

  const steps: StepItem[] = [
    { title: t('data-importer.data-setup.steps.data-source.title') },
    { title: t('data-importer.data-setup.steps.preview-import.title') },
    { title: t('data-importer.data-setup.steps.resolver.title') },
    { title: t('data-importer.data-setup.steps.mapping.title') },
    { title: t('data-importer.data-setup.steps.processing-settings.title') }
  ]

  const isMappingStep = currentStep === MAPPING_STEP_INDEX

  return (
    <Flex
      className={ styles.tabLayout }
      vertical
    >
      <Box margin={ { x: 'small' } }>
        <Steps
          current={ currentStep }
          items={ steps }
          onChange={ setCurrentStep }
          size="small"
          type="navigation"
        />
      </Box>

      <div className={ `${styles.stepContentMapping}${isMappingStep ? '' : ` ${styles.stepContentMappingHidden}`}` }>
        <MappingStep
          configName={ configName }
          isActive={ isMappingStep }
        />
      </div>

      <div className={ `${styles.stepContent}${currentStep === 0 ? '' : ` ${styles.stepContentHidden}`}` }>
        <DataSourceStep configName={ configName } />
      </div>

      <div className={ `${styles.stepContent}${currentStep === 1 ? '' : ` ${styles.stepContentHidden}`}` }>
        <PreviewImportStep
          configName={ configName }
          isActive={ currentStep === 1 }
        />
      </div>

      <div className={ `${styles.stepContent}${currentStep === 2 ? '' : ` ${styles.stepContentHidden}`}` }>
        <ResolverStep
          columnHeaderOptions={ columnHeaderOptions }
          configName={ configName }
        />
      </div>

      <div className={ `${styles.stepContent}${currentStep === 4 ? '' : ` ${styles.stepContentHidden}`}` }>
        <ProcessingSettingsStep configName={ configName } />
      </div>
    </Flex>
  )
}
