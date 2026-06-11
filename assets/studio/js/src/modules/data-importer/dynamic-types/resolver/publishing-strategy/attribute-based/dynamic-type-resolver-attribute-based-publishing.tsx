import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { PathLoadingStrategyResolverSettings } from './attribute-based-publishing-strategy-resolver-settings';

@injectable()
export class DynamicTypeResolverAttributeBasedPublishing extends DynamicTypeResolverAbstract {
    readonly id: 'attributeBased';
    readonly label: 'data-importer.resolver.publishing-strategy.attributeBased';
    readonly group: 'publishing';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <PathLoadingStrategyResolverSettings {...props} />;
    }
}
