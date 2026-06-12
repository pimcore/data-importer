/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { IdLoadingStrategyResolverSettings } from './id-loading-strategy-resolver-settings';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverId extends DynamicTypeResolverAbstract {
    readonly id = 'id';
    readonly value = 'id';
    readonly label = 'data-importer.resolver.loading-strategy.id';
    readonly group = 'loading';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <IdLoadingStrategyResolverSettings {...props} />;
    }
}
