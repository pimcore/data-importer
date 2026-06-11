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
