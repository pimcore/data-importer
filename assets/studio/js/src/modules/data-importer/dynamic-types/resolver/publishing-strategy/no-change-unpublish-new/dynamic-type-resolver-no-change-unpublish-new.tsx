import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverNoChangeUnpublishNew extends DynamicTypeResolverAbstract {
    readonly id: 'noChangeUnpublishNew';
    readonly value: 'noChangeUnpublishNew';
    readonly label: 'data-importer.resolver.publishing-strategy.noChangeUnpublishNew';
    readonly group: 'publishing';
    renderSettings() {
        return null;
    }
}
