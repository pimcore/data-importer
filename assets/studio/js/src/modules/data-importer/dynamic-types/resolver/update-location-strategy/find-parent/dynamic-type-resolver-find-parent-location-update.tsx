import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { FindParentLocationUpdateSettings } from './find-parent-location-update-settings';

@injectable()
export class DynamicTypeResolverFindParentLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id: 'update.findParent';
    readonly value: 'findParent';
    readonly label: 'data-importer.resolver.location-strategy.findParent';
    readonly group: 'updateLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindParentLocationUpdateSettings {...props} />;
    }
}
