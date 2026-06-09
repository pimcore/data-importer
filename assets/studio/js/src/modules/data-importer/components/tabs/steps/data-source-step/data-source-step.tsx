/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { Form, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { StepHeading } from '../step-heading/step-heading'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { filterByLabel } from '../../../../utils/select-utils'
import type { DataImporterFormValues } from '../../../../types'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { container } from '@pimcore/studio-ui-bundle'
import { bundleServiceIds } from '../../../../../../config/service-ids'
import { type DynamicTypeLoaderRegistry } from '../../../../dynamic-types/loader/dynamic-type-loader-registry'
import { type DynamicTypeInterpreterRegistry } from '../../../../dynamic-types/interpreter/dynamic-type-interpreter-registry'

export interface DataSourceStepProps {
  configName: string
}

export const DataSourceStep = ({ configName }: DataSourceStepProps): React.JSX.Element => {
  const { t } = useTranslation()

  const loaderRegistry = useMemo(
    () => container.get<DynamicTypeLoaderRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Loader/Registry']),
    []
  )

  const interpreterRegistry = useMemo(
    () =>
      container.get<DynamicTypeInterpreterRegistry>(
        bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Registry']
      ),
    []
  )

  const loaderDynamicTypes = useMemo(() => loaderRegistry.getDynamicTypes(), [loaderRegistry])
  const interpreterDynamicTypes = useMemo(() => interpreterRegistry.getDynamicTypes(), [interpreterRegistry])

  const loaderTypes = useMemo(
    () => loaderDynamicTypes.map(({ id, label }) => ({ value: id, label: t(label) })),
    [loaderDynamicTypes, t]
  )

  const interpreterTypes = useMemo(
    () => interpreterDynamicTypes.map(({ id, label }) => ({ value: id, label: t(label) })),
    [interpreterDynamicTypes, t]
  )

  return (
    <FieldWidthProvider fieldWidthValues={ { medium: 900 } }>
      <StepHeading>{t('data-importer.data-source.title')}</StepHeading>

      <DataImporterPanel>
        <Form.Item
          label={ t('data-importer.data-source.type-label') }
          name={ ['loaderConfig', 'type'] }
          required
        >
          <Select
            filterOption={ filterByLabel }
            options={ loaderTypes }
            showSearch
          />
        </Form.Item>

        {loaderDynamicTypes.map((loaderType) => (
          <Form.Conditional
            condition={ (values) =>
              (values as unknown as DataImporterFormValues).loaderConfig?.type === loaderType.id
                        }
            key={ loaderType.id }
          >
            <DataImporterPanel
              theme="fieldset"
              title={ t(loaderType.label) }
            >
              {loaderType.renderSettings(configName)}
            </DataImporterPanel>
          </Form.Conditional>
        ))}
      </DataImporterPanel>

      <DataImporterPanel title={ t('data-importer.file-format.title') }>
        <Form.Item
          label={ t('data-importer.file-format.title') }
          name={ ['interpreterConfig', 'type'] }
          required
        >
          <Select
            filterOption={ filterByLabel }
            options={ interpreterTypes }
            showSearch
          />
        </Form.Item>

        {interpreterDynamicTypes.map((interpreterType) => (
          <Form.Conditional
            condition={ (values) =>
              (values as unknown as DataImporterFormValues).interpreterConfig?.type === interpreterType.id
                        }
            key={ interpreterType.id }
          >
            <DataImporterPanel
              border
              theme="fieldset"
              title={ t(interpreterType.label) }
            >
              {interpreterType.renderSettings()}
            </DataImporterPanel>
          </Form.Conditional>
        ))}
      </DataImporterPanel>
    </FieldWidthProvider>
  )
}
