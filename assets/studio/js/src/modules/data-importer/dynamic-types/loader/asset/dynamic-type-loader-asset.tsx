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
import { DynamicTypeLoaderAbstract } from '../dynamic-type-loader-abstract'
import { AssetLoaderSettings } from './asset-loader-settings'

@injectable()
export class DynamicTypeLoaderAsset extends DynamicTypeLoaderAbstract {
  readonly id = 'asset'
  readonly label = 'data-importer.loader.asset'

  renderSettings (_configName: string): React.JSX.Element | null {
    return <AssetLoaderSettings />
  }
}
