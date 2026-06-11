import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';

export class DynamicTypeResolverNotLoad extends DynamicTypeResolverAbstract {
    readonly id: 'notLoad';
    readonly value: 'notLoad';
    readonly label: 'data-importer.resolver.loading-strategy.notLoad';
    readonly group: 'loading';
    renderSettings() {
        return null;
    }
}
