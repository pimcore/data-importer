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
import { ConditionalConversionTransformerForm } from './conditional-conversion-transformer-form'

@injectable()
export class DynamicTypeTransformerConditionalConversion extends DynamicTypeTransformerAbstract {
  readonly id = 'conditionalConversion'
  readonly label = 'Conditional Conversion'
  readonly group = 'dataManipulation' as const

  renderSettings (
    settings: Record<string, any>,
    onChange: (settings: Record<string, any>) => void
  ): React.JSX.Element | null {
    return (
      <ConditionalConversionTransformerForm
        onChange={ onChange }
        settings={ settings }
      />
    )
  }
}
