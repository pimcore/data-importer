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
import { FindParentLocationCreationSettings } from './find-parent-location-creation-settings';

@injectable()
export class DynamicTypeResolverFindParentLocationCreation extends DynamicTypeResolverAbstract {
    readonly id: 'creation.findParent';
    readonly value: 'findParent';
    readonly label: 'data-importer.resolver.location-strategy.findParent';
    readonly group: 'createLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindParentLocationCreationSettings {...props} />;
    }
}
