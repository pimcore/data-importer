import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import React from 'react';

export class DynamicTypeResolverNotLoad extends DynamicTypeResolverAbstract {
    readonly id: 'notLoad';
    readonly label: 'data-importer.resolver.loading-strategy.notLoad';
    readonly group: 'loading';
    renderSettings(): React.JSX.Element | null {
        return null;
    }
}
