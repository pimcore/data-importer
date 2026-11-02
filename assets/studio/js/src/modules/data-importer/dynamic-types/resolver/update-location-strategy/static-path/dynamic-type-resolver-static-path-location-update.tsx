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
import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { StaticPathUpdateLocationStrategyResolverSettings } from './static-path-update-location-strategy-resolver-settings'

@injectable()
export class DynamicTypeResolverStaticPathLocationUpdate extends DynamicTypeResolverAbstract {
  readonly id = 'updateLocation.staticPath'
  readonly type = 'staticPath'
  readonly label = 'data-importer.resolver.location-strategy.staticPath'
  readonly group = 'updateLocation'
  renderSettings (): React.JSX.Element {
    return <StaticPathUpdateLocationStrategyResolverSettings />
  }
}
