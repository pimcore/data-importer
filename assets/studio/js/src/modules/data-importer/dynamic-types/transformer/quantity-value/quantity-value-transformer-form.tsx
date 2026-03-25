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
import { Select, Form, Switch } from '@pimcore/studio-ui-bundle/components'
import { TransformerSettingsLayout } from '../transformer-settings-layout'
import { useBundleDataImporterDataTypeLoadUnitDataQuery } from '../../../data-importer-api-slice.gen'

interface QuantityValueTransformerConfig {
  unitSourceSelect?: string
  staticUnitSelect?: string
  unitNullIfNoValueCheckbox?: boolean
}

interface QuantityValueTransformerFormProps {
  settings: QuantityValueTransformerConfig
  onChange: (settings: QuantityValueTransformerConfig) => void
}

export const QuantityValueTransformerForm = ({ settings, onChange }: QuantityValueTransformerFormProps): React.JSX.Element => {
  const update = (key: string, value: any): void => { onChange({ ...settings, [key]: value }) }

  const unitSourceSelect: string = settings.unitSourceSelect ?? 'id'

  const { data: unitData, isLoading: isLoadingUnits } = useBundleDataImporterDataTypeLoadUnitDataQuery()
  const unitOptions = useMemo(
    () => (unitData?.UnitList ?? []).map((u) => ({
      value: u.unitId ?? '',
      label: u.abbreviation ?? u.unitId ?? ''
    })),
    [unitData]
  )

  return (
    <TransformerSettingsLayout>
      { (styles) => (
        <>
          <Form.Item
            className={ styles.formItem }
            label={ <span className={ styles.label }>Unit source</span> }
          >
            <Select
              onChange={ (v) => {
                onChange({ ...settings, unitSourceSelect: v, staticUnitSelect: undefined })
              } }
              options={ [
                { value: 'id', label: 'By Unit ID' },
                { value: 'abbr', label: 'By Abbreviation' },
                { value: 'static', label: 'Static' }
              ] }
              value={ unitSourceSelect }
            />
          </Form.Item>

          { unitSourceSelect === 'static' && (
            <Form.Item
              className={ styles.formItem }
              label={ <span className={ styles.label }>Unit</span> }
            >
              <Select
                loadingSkeleton={ isLoadingUnits }
                onChange={ (v) => { update('staticUnitSelect', v) } }
                options={ unitOptions }
                showSearch
                value={ settings.staticUnitSelect }
              />
            </Form.Item>
          ) }

          <Form.Item className={ styles.formItemLast }>
            <Switch
              checked={ Boolean(settings.unitNullIfNoValueCheckbox) }
              labelRight="Null if no value"
              onChange={ (v) => { update('unitNullIfNoValueCheckbox', v) } }
              size="small"
            />
          </Form.Item>
        </>
      ) }
    </TransformerSettingsLayout>
  )
}
