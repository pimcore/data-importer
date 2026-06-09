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
import { StepTargetAttributeSelect } from '../common/step-target-attribute-select'
import { StepTargetAttributeLanguageSelect } from '../common/step-target-attribute-language-select'
import { StepTargetClassificationstoreKeySearch } from './step-target-classificationstore-key-search'
import { useClassificationStoreSettings } from './use-classification-store-settings'

export function DataTargetClassificationstoreSettings (props: DynamicTypeDataTargetRenderProps): React.JSX.Element {
  const { classId, settings, transformationResultType, onChange } = props
  const { options, isLocalized, isFetching } = useClassificationStoreSettings(props)
  const fieldName = settings?.fieldName

  return (
    <>
      <StepTargetAttributeSelect
        isLoading={ isFetching }
        onChange={ (value) => { onChange({ ...settings, fieldName: value }) } }
        options={ options }
        value={ settings?.fieldName }
      />

      {fieldName !== undefined && fieldName !== '' && (
        <StepTargetClassificationstoreKeySearch
          classId={ classId ?? '' }
          fieldName={ fieldName }
          onChange={ (keyId) => { onChange({ ...settings, keyId }) } }
          transformationResultType={ transformationResultType }
        />
      )}

      {isLocalized && (
        <StepTargetAttributeLanguageSelect
          onChange={ (language) => { onChange({ ...settings, language }) } }
          value={ settings?.language }
        />
      )}
    </>
  )
}
