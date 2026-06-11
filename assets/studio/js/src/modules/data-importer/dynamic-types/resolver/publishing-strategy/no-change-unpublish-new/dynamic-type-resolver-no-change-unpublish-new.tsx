import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';

export class DynamicTypeResolverNoChangeUnpublishNew extends DynamicTypeResolverAbstract {
    readonly id: 'noChangeUnpublishNew';
    readonly label: 'data-importer.resolver.publishing-strategy.id';
    readonly group: 'publishing';
    renderSettings() {
        return null;
    }
}
