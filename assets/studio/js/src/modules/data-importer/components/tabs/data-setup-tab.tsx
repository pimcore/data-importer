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
import { Steps, type StepItem, Space } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataSourceStep } from './steps/data-source-step'
import { PreviewImportStep } from './steps/preview-import-step'
import { ResolverStep } from './steps/resolver-step'
import { MappingStep } from './steps/mapping-step'
import { ProcessingSettingsStep } from './steps/processing-settings-step'

export interface DataSetupTabProps {
  configName: string
}

export const DataSetupTab = ({ configName }: DataSetupTabProps): React.JSX.Element => {
  const { t } = useTranslation()
  const [currentStep, setCurrentStep] = useState(0)

  const steps: StepItem[] = [
    {
      title: t('data-importer.data-setup.steps.data-source.title')
    },
    {
      title: t('data-importer.data-setup.steps.preview-import.title')
    },
    {
      title: t('data-importer.data-setup.steps.resolver.title')
    },
    {
      title: t('data-importer.data-setup.steps.mapping.title')
    },
    {
      title: t('data-importer.data-setup.steps.processing-settings.title')
    }
  ]

  const renderStepContent = (): React.JSX.Element => {
    switch (currentStep) {
      case 0:
        return <DataSourceStep configName={ configName } />
      case 1:
        return <PreviewImportStep configName={ configName } />
      case 2:
        return <ResolverStep configName={ configName } />
      case 3:
        return <MappingStep configName={ configName } />
      case 4:
        return <ProcessingSettingsStep configName={ configName } />
      default:
        return <DataSourceStep configName={ configName } />
    }
  }

  return (
    <Space
      direction="vertical"
      size="large"
      style={ { width: '100%' } }
    >
      <Steps
        current={ currentStep }
        items={ steps }
        onChange={ setCurrentStep }
        size="small"
        type="navigation"
      />
      {renderStepContent()}
    </Space>
  )
}
