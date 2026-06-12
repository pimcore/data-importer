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
import {
  DynamicTypeResolverAbstract,
  type DynamicTypeResolverRenderProps
} from '../../common/dynamic-type-resolver-abstract'
import { PathLoadingStrategyResolverSettings } from './path-loading-strategy-resolver-settings'
import { injectable } from '@pimcore/studio-ui-bundle/app'

@injectable()
export class DynamicTypeResolverPath extends DynamicTypeResolverAbstract {
  readonly id = 'loading.path'
  readonly type = 'path'
  readonly label = 'data-importer.resolver.loading-strategy.path'
  readonly group = 'loading'
  renderSettings (props: DynamicTypeResolverRenderProps): React.JSX.Element {
    return <PathLoadingStrategyResolverSettings { ...props } />
  }
}
