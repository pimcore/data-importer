/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract'
import { injectable } from '@pimcore/studio-ui-bundle/app'

@injectable()
export class DynamicTypeResolverNoChangeLocationUpdate extends DynamicTypeResolverAbstract {
  readonly id = 'updateLocation.noChange'
  readonly type = 'noChange'
  readonly label = 'data-importer.resolver.location-strategy.noChange'
  readonly group = 'updateLocation'
  renderSettings (): null {
    return null
  }
}
