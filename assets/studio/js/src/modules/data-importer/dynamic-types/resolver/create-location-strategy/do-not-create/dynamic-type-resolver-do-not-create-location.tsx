import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverDoNotCreateLocation extends DynamicTypeResolverAbstract {
    readonly id: 'creation.doNotCreate';
    readonly value: 'doNotCreate';
    readonly label: 'data-importer.resolver.location-strategy.doNotCreate';
    readonly group: 'createLocation';
    renderSettings() {
        return null;
    }
}
