/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { injectable } from '@pimcore/studio-ui-bundle/app';
import { DynamicTypeRegistryAbstract } from '@pimcore/studio-ui-bundle/modules/element';
import { type DynamicTypeResolverAbstract, ResolverGroup } from './common/dynamic-type-resolver-abstract';

@injectable()
export class DynamicTypeResolverRegistry extends DynamicTypeRegistryAbstract<DynamicTypeResolverAbstract> {
    getDynamicTypesForGroup(group: ResolverGroup): DynamicTypeResolverAbstract[] {
        return this.getDynamicTypes().filter((t) => t.group === group);
    }
}
