import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverNoChangeLocationUpdate extends DynamicTypeResolverAbstract {
    readonly id: 'update.noChange';
    readonly value: 'noChange';
    readonly label: 'data-importer.resolver.location-strategy.noChange';
    readonly group: 'updateLocation';
    renderSettings() {
        return null;
    }
}
