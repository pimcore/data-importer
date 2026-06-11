import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverAlwaysPublish extends DynamicTypeResolverAbstract {
    readonly id: 'alwaysPublish';
    readonly label: 'data-importer.resolver.publishing-strategy.alwaysPublish';
    readonly group: 'publishing';
    renderSettings() {
        return null;
    }
}
