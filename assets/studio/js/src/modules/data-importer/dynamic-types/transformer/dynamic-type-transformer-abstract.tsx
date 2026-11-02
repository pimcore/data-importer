/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import type React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { DynamicTypeAbstract } from '@pimcore/studio-ui-bundle/modules/element'

export type TransformerGroup = 'dataManipulation' | 'dataTypes' | 'loadImport'

@injectable()
export abstract class DynamicTypeTransformerAbstract extends DynamicTypeAbstract {
  /** Unique identifier, e.g. 'trim', 'combine', 'staticText' */
  abstract readonly id: string

  /** Human-readable label shown in the UI */
  abstract readonly label: string

  /** Group for the dropdown submenu */
  abstract readonly group: TransformerGroup

  /**
   * Render the settings form for this transformer type.
   * Return null if there are no settings.
   */
  abstract renderSettings (
    settings: Record<string, any>,
    onChange: (settings: Record<string, any>) => void
  ): React.JSX.Element | null
}
