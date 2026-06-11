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
import { UploadLoaderSettings } from './upload-loader-settings'

@injectable()
export class DynamicTypeLoaderUpload extends DynamicTypeLoaderAbstract {
  readonly id = 'upload'
  readonly label = 'data-importer.loader.upload'

  renderSettings (configName: string): React.JSX.Element | null {
    return <UploadLoaderSettings configName={ configName } />
  }
}
