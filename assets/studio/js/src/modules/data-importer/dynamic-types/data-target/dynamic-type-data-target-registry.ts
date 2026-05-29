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
import { DynamicTypeDataTargetAbstract } from './common/dynamic-type-data-target-abstract';

@injectable()
export class DynamicTypeDataTargetRegistry {
    private readonly types = new Map<string, DynamicTypeDataTargetAbstract>();

    registerDynamicType(type: DynamicTypeDataTargetAbstract): void {
        this.types.set(type.id, type);
    }

    getDynamicType(id: string): DynamicTypeDataTargetAbstract | undefined {
        return this.types.get(id);
    }

    getAllTypes(): DynamicTypeDataTargetAbstract[] {
        return Array.from(this.types.values());
    }
}
