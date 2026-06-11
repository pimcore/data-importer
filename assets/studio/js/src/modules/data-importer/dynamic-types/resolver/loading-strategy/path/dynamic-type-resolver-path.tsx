import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { PathLoadingStrategyResolverSettings } from './path-loading-strategy-resolver-settings';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverPath extends DynamicTypeResolverAbstract {
    readonly id: 'path';
    readonly label: 'data-importer.resolver.loading-strategy.path';
    readonly group: 'loading';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <PathLoadingStrategyResolverSettings {...props} />;
    }
}
