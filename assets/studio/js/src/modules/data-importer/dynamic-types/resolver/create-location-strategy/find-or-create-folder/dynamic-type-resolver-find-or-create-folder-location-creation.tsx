import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { FindOrCreateFolderLocationCreationSettings } from './find-or-create-folder-location-creation-settings';

@injectable()
export class DynamicTypeResolverFindOrCreateFolderLocationCreation extends DynamicTypeResolverAbstract {
    readonly id: 'creation.findOrCreateFolderLocationCreation';
    readonly value: 'findOrCreateFolderLocationCreation';
    readonly label: 'data-importer.resolver.location-strategy.staticPath';
    readonly group: 'createLocation';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <FindOrCreateFolderLocationCreationSettings {...props} />;
    }
}
