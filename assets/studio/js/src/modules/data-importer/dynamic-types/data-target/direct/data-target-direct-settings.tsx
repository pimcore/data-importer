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
import { type DynamicTypeDataTargetRenderProps } from '../common/dynamic-type-data-target-abstract'
import { StepTargetWriteSettings } from '../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target-write-settings'
import { StepTargetAttributeSelect } from '../common/step-target-attribute-select'
import { StepTargetAttributeLanguageSelect } from '../common/step-target-attribute-language-select'

export function DataTargetDirectSettings ({
  isLocalized,
  settings,
  onChange,
  classFieldOptions
}: DynamicTypeDataTargetRenderProps): React.JSX.Element {
  return (
    <>
      <StepTargetAttributeSelect
        onChange={ (value) => { onChange({ ...settings, fieldName: value }) } }
        options={ classFieldOptions }
        value={ settings?.fieldName }
      />

      {isLocalized && (
        <StepTargetAttributeLanguageSelect
          onChange={ (language) => { onChange({ ...settings, language }) } }
          value={ settings?.language }
        />
      )}

      <StepTargetWriteSettings
        onChange={ onChange }
        settings={ settings }
      />
    </>
  )
}
