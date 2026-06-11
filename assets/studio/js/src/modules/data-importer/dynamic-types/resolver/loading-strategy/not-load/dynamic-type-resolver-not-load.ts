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

export class DynamicTypeResolverNotLoad extends DynamicTypeResolverAbstract {
    readonly id: 'notLoad';
    readonly value: 'notLoad';
    readonly label: 'data-importer.resolver.loading-strategy.notLoad';
    readonly group: 'loading';
    renderSettings() {
        return null;
    }
}
