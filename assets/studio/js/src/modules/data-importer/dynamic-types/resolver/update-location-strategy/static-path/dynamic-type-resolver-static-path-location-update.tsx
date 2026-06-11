import React from 'react';
import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { StaticPathUpdateLocationStrategyResolverSettings } from './static-path-update-location-strategy-resolver-settings';

@injectable()
export class DynamicTypeResolverStaticPathLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id: 'update.staticPath';
    readonly value: 'staticPath';
    readonly label: 'data-importer.resolver.location-strategy.staticPath';
    readonly group: 'updateLocation';
    renderSettings() {
        return <StaticPathUpdateLocationStrategyResolverSettings />;
    }
}
