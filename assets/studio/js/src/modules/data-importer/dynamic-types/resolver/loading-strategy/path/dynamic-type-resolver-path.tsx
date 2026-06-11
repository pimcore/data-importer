import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { PathLoadingStrategyResolverSettings } from './path-loading-strategy-resolver-settings';

export class DynamicTypeResolverPath extends DynamicTypeResolverAbstract {
    readonly id: 'path';
    readonly label: 'data-importer.resolver.loading-strategy.path';
    readonly group: 'loading';
    renderSettings(props: DynamicTypeResolverRenderProps): React.JSX.Element | null {
        return <PathLoadingStrategyResolverSettings {...props} />;
    }
}
