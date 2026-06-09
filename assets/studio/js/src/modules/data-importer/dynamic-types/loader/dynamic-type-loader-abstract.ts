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

@injectable()
export abstract class DynamicTypeLoaderAbstract extends DynamicTypeAbstract {
  /** Unique identifier, e.g. 'asset', 'upload', 'http' */
  abstract readonly id: string

  /** Human-readable label shown in the UI */
  abstract readonly label: string

  /** Render the settings fields for this loader type */
  abstract renderSettings (configName: string): React.JSX.Element | null
}
