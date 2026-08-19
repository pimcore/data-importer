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
import { Form, Select } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { filterByLabel } from '../../../../../utils/select-utils'

export interface SourcePickerContentProps {
  options: Array<{ value: string, label: string }>
  valueRef: React.MutableRefObject<string | undefined>
  errorRef: React.MutableRefObject<((show: boolean) => void) | undefined>
}

export const SourcePickerContent = (props: SourcePickerContentProps): React.JSX.Element => {
  const { options, valueRef, errorRef } = props
  const { t } = useTranslation()
  const [value, setValue] = useState<string | undefined>(undefined)
  const [hasError, setHasError] = useState(false)

  errorRef.current = setHasError

  const handleChange = (v: string | undefined): void => {
    setValue(v)
    valueRef.current = v
    if (v !== undefined) {
      setHasError(false)
    }
  }

  return (
    <Form.Item
      help={ hasError ? t('data-importer.mapping.new-modal.source-required') : undefined }
      label={ t('data-importer.mapping.new-modal.source-label') }
      required
      style={ { marginTop: 12, marginBottom: 0 } }
      validateStatus={ hasError ? 'error' : undefined }
    >
      <Select
        allowClear
        filterOption={ filterByLabel }
        onChange={ handleChange }
        options={ options }
        placeholder={ t('data-importer.mapping.item.source-placeholder') }
        showSearch
        style={ { width: '100%' } }
        value={ value }
      />
    </Form.Item>
  )
}
