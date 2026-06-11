import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverNoChangePublishNew extends DynamicTypeResolverAbstract {
    readonly id: 'noChangePublishNew';
    readonly value: 'noChangePublishNew';
    readonly label: 'data-importer.resolver.publishing-strategy.noChangePublishNew';
    readonly group: 'publishing';
    renderSettings() {
        return null;
    }
}
