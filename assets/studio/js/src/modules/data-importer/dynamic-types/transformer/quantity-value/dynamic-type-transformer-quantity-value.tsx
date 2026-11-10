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
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { DynamicTypeTransformerAbstract } from '../dynamic-type-transformer-abstract'
import { QuantityValueTransformerForm } from './quantity-value-transformer-form'

function makeQuantityValueTransformer (id: string, label: string, defaultUnitSource: string): new () => DynamicTypeTransformerAbstract {
  @injectable()
  class QuantityValueTransformerType extends DynamicTypeTransformerAbstract {
    readonly id = id
    readonly label = label
    readonly group = 'dataTypes' as const

    renderSettings (
      settings: Record<string, any>,
      onChange: (settings: Record<string, any>) => void
    ): React.JSX.Element | null {
      return (
        <QuantityValueTransformerForm
          defaultUnitSource={ defaultUnitSource }
          onChange={ onChange }
          settings={ settings }
        />
      )
    }
  }
  return QuantityValueTransformerType
}

// The default unit source of each operator is its behaviour before the options existed
export const DynamicTypeTransformerQuantityValue = makeQuantityValueTransformer('quantityValue', 'Quantity Value', 'id')
export const DynamicTypeTransformerQuantityValueArray = makeQuantityValueTransformer('quantityValueArray', 'Quantity Value Array', 'id')
export const DynamicTypeTransformerInputQuantityValue = makeQuantityValueTransformer('inputQuantityValue', 'Input Quantity Value', 'abbr')
export const DynamicTypeTransformerInputQuantityValueArray = makeQuantityValueTransformer('inputQuantityValueArray', 'Input Quantity Value Array', 'id')
