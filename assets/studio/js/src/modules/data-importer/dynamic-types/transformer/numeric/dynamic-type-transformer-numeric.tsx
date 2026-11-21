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
import { NumericTransformerForm } from './numeric-transformer-form'

@injectable()
export class DynamicTypeTransformerNumeric extends DynamicTypeTransformerAbstract {
  readonly id = 'numeric'
  readonly label = 'Numeric'
  readonly group = 'dataTypes' as const

  renderSettings (
    settings: Record<string, any>,
    onChange: (settings: Record<string, any>) => void
  ): React.JSX.Element | null {
    return (
      <NumericTransformerForm
        onChange={ onChange }
        settings={ settings }
      />
    )
  }
}
