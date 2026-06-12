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
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { FindParentLocationUpdateSettings } from './find-parent-location-update-settings';

@injectable()
export class DynamicTypeResolverFindParentLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id = 'update.findParent';
    readonly type = 'findParent';
    readonly label = 'data-importer.resolver.location-strategy.findParent';
    readonly group = 'updateLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindParentLocationUpdateSettings {...props} />;
    }
}
