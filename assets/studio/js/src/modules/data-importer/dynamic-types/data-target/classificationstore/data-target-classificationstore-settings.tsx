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
import { StepTargetClassificationstoreWriteSettings } from './step-target-classificationstore-write-settings'
import { StepTargetClassificationstoreVariantSelect } from './step-target-classificationstore-variant-select'

export function DataTargetClassificationstoreSettings (props: DynamicTypeDataTargetRenderProps): React.JSX.Element {
  const { classId, settings, transformationResultType, onChange } = props
  const { options, isKeyLocalized, isKeyUnanswered, isFetching, variants } = useClassificationStoreSettings(props)
  const fieldName = settings?.fieldName
  const currentKeyId: string = settings?.keyId ?? ''

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
          keyId={ settings?.keyId }
          onChange={ (keyId) => { onChange({ ...settings, keyId }) } }
          transformationResultType={ transformationResultType }
        />
      )}

      {(isKeyLocalized || isKeyUnanswered) && (
        <StepTargetAttributeLanguageSelect
          onChange={ (language) => { onChange({ ...settings, language }) } }
          value={ settings?.language }
        />
      )}

      {variants.length > 0 && (
        <StepTargetClassificationstoreVariantSelect
          keyId={ currentKeyId }
          onChange={ (value) => { onChange({ ...settings, keyId: value }) } }
          variants={ variants }
        />
      )}

      <StepTargetClassificationstoreWriteSettings
        onChange={ onChange }
        settings={ settings }
        target="classificationstore"
      />
    </>
  )
}
