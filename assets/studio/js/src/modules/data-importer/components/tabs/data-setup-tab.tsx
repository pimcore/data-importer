/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo, useState } from 'react'
import cn from 'classnames'
import { Steps, type StepItem, Flex } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataSourceStep } from './steps/data-source-step'
import { PreviewImportStep } from './steps/preview-import-step'
import { ResolverStep } from './steps/resolver-step'
import { MappingStep } from './steps/mapping-step'
import { ProcessingSettingsStep } from './steps/processing-settings-step'
import { useStyles } from './data-setup-tab.styles'
import { useColumnHeaderOptions } from '../../hooks/use-column-header-options'
import { Box } from '@pimcore/studio-ui-bundle/components'

export interface DataSetupTabProps {
  configName: string
}

export const DataSetupTab = ({ configName }: DataSetupTabProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const [currentStep, setCurrentStep] = useState(0)
  // Bumped on preview-data change so dependent steps refresh their column lists.
  const [previewVersion, setPreviewVersion] = useState(0)

  const RESOLVER_STEP_INDEX = 2
  const PROCESSING_STEP_INDEX = 4

  // Shared column options for the resolver and processing steps; loaded while either is active.
  const columnHeaderOptions = useColumnHeaderOptions(
    configName,
    currentStep === RESOLVER_STEP_INDEX || currentStep === PROCESSING_STEP_INDEX,
    previewVersion
  )

  const steps: StepItem[] = useMemo(() => [
    { title: t('data-importer.data-setup.steps.data-source.title') },
    { title: t('data-importer.data-setup.steps.preview-import.title') },
    { title: t('data-importer.data-setup.steps.resolver.title') },
    { title: t('data-importer.data-setup.steps.mapping.title') },
    { title: t('data-importer.data-setup.steps.processing-settings.title') }
  ], [t])

  const MAPPING_STEP_INDEX = 3
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

      <div className={ cn(styles.stepContentMapping, !isMappingStep && styles.stepContentMappingHidden) }>
        <MappingStep
          configName={ configName }
          isActive={ isMappingStep }
        />
      </div>

      <div className={ cn(styles.stepContent, currentStep !== 0 && styles.stepContentHidden) }>
        <DataSourceStep configName={ configName } />
      </div>

      <div className={ cn(styles.stepContent, currentStep !== 1 && styles.stepContentHidden) }>
        <PreviewImportStep
          configName={ configName }
          isActive={ currentStep === 1 }
          onPreviewDataChange={ () => { setPreviewVersion((v) => v + 1) } }
        />
      </div>

      <div className={ cn(styles.stepContent, currentStep !== 2 && styles.stepContentHidden) }>
        <ResolverStep
          columnHeaderOptions={ columnHeaderOptions }
          configName={ configName }
          isActive={ currentStep === 2 }
        />
      </div>

      <div className={ cn(styles.stepContent, currentStep !== 4 && styles.stepContentHidden) }>
        <ProcessingSettingsStep columnHeaderOptions={ columnHeaderOptions } />
      </div>
    </Flex>
  )
}
