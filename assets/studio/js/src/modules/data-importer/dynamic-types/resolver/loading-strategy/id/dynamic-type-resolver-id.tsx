import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { IdLoadingStrategyResolverSettings } from './id-loading-strategy-resolver-settings';

export class DynamicTypeResolverId extends DynamicTypeResolverAbstract {
    readonly id: 'id';
    readonly label: 'data-importer.resolver.loading-strategy.id';
    readonly group: 'loading';
    renderSettings(props: DynamicTypeResolverRenderProps): React.JSX.Element | null {
        return <IdLoadingStrategyResolverSettings {...props} />;
    }
}
