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
import { Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { filterByLabel } from '../../select-utils'

export interface SourcePickerContentProps {
  options: Array<{ value: string, label: string }>
  valueRef: React.MutableRefObject<string | undefined>
  errorRef: React.MutableRefObject<((show: boolean) => void) | undefined>
}

/**
 * Small controlled component rendered inside the "New mapping" confirm modal.
 * It writes the selected value into valueRef on every change so the caller
 * can read it in onOk without any async state.
 */
export const SourcePickerContent = (props: SourcePickerContentProps): React.JSX.Element => {
  const { options, valueRef, errorRef } = props
  const { t } = useTranslation()
  const [value, setValue] = useState<string | undefined>(undefined)
  const [hasError, setHasError] = useState(false)

  // Register the error setter so the parent (handleAddItem) can trigger it
  errorRef.current = setHasError

  const handleChange = (v: string | undefined): void => {
    setValue(v)
    valueRef.current = v
    if (v !== undefined) {
      setHasError(false)
    }
  }

  return (
    <div style={ { marginTop: 12 } }>
      <div style={ { marginBottom: 4 } }>
        { t('data-importer.mapping.new-modal.source-label') }
        <span style={ { color: '#ff4d4f', marginLeft: 4 } }>*</span>
      </div>
      <Select
        allowClear
        filterOption={ filterByLabel }
        onChange={ handleChange }
        options={ options }
        placeholder={ t('data-importer.mapping.item.source-placeholder') }
        showSearch
        status={ hasError ? 'error' : undefined }
        style={ { width: '100%' } }
        value={ value }
      />
      { hasError && (
        <div style={ { color: '#ff4d4f', fontSize: 12, marginTop: 4 } }>
          { t('data-importer.mapping.new-modal.source-required') }
        </div>
      ) }
    </div>
  )
}
