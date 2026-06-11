import React from 'react';
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { AttributeLoadingStrategyResolverSettings } from './attribute-loading-strategy-resolver-settings';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverAttribute extends DynamicTypeResolverAbstract {
    readonly id: 'attribute';
    readonly value: 'attribute';
    readonly label: 'data-importer.resolver.loading-strategy.attribute';
    readonly group: 'loading';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <AttributeLoadingStrategyResolverSettings {...props} />;
    }
}
