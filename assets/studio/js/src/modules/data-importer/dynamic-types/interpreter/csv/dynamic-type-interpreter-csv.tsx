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
import { DynamicTypeInterpreterAbstract } from '../dynamic-type-interpreter-abstract'
import { CsvInterpreterSettings } from './csv-interpreter-settings'

@injectable()
export class DynamicTypeInterpreterCsv extends DynamicTypeInterpreterAbstract {
  readonly id = 'csv'
  readonly label = 'CSV'
  renderSettings (): React.JSX.Element | null {
    return <CsvInterpreterSettings />
  }
}
