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
import { XlsxInterpreterSettings } from './xlsx-interpreter-settings'

@injectable()
export class DynamicTypeInterpreterXlsx extends DynamicTypeInterpreterAbstract {
  readonly id = 'xlsx'
  readonly label = 'data-importer.interpreter.xlsx'
  renderSettings (): React.JSX.Element | null {
    return <XlsxInterpreterSettings />
  }
}
