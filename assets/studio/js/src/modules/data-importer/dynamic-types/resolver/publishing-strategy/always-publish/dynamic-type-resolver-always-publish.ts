/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';

@injectable()
export class DynamicTypeResolverAlwaysPublish extends DynamicTypeResolverAbstract {
    readonly id: 'alwaysPublish';
    readonly value: 'alwaysPublish';
    readonly label: 'data-importer.resolver.publishing-strategy.alwaysPublish';
    readonly group: 'publishing';
    renderSettings() {
        return null;
    }
}
