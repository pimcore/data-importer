import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { FindOrCreateFolderLocationUpdateSettings } from './find-or-create-folder-location-update-settings';

@injectable()
export class DynamicTypeResolverFindOrCreateFolderLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id: 'creation.findOrCreateFolder';
    readonly value: 'findOrCreateFolder';
    readonly label: 'data-importer.resolver.location-strategy.findOrCreateFolder';
    readonly group: 'updateLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindOrCreateFolderLocationUpdateSettings {...props} />;
    }
}
