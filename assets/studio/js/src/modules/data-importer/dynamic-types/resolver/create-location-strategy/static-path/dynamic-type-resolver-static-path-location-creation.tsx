import React from 'react';
import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { StaticPathCreateLocationStrategyResolverSettings } from './static-path-create-location-strategy-resolver-settings';

@injectable()
export class DynamicTypeResolverStaticPathLocationCreation extends DynamicTypeResolverAbstract {
    readonly id: 'creation.staticPath';
    readonly value: 'staticPath';
    readonly label: 'data-importer.resolver.location-strategy.staticPath';
    readonly group: 'createLocation';
    renderSettings() {
        return <StaticPathCreateLocationStrategyResolverSettings />;
    }
}
