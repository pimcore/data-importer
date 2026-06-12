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
import { FindOrCreateFolderLocationUpdateSettings } from './find-or-create-folder-location-update-settings';

@injectable()
export class DynamicTypeResolverFindOrCreateFolderLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id = 'updateLocation.findOrCreateFolder';
    readonly type = 'findOrCreateFolder';
    readonly label = 'data-importer.resolver.location-strategy.findOrCreateFolder';
    readonly group = 'updateLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindOrCreateFolderLocationUpdateSettings {...props} />;
    }
}
