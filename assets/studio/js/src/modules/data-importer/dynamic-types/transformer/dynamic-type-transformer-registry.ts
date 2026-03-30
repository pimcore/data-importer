/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { injectable } from '@pimcore/studio-ui-bundle/app'
import { type DynamicTypeTransformerAbstract } from './dynamic-type-transformer-abstract'

@injectable()
export class DynamicTypeTransformerRegistry {
  private readonly types = new Map<string, DynamicTypeTransformerAbstract>()

  registerDynamicType (type: DynamicTypeTransformerAbstract): void {
    this.types.set(type.id, type)
  }

  getDynamicType (id: string): DynamicTypeTransformerAbstract | undefined {
    return this.types.get(id)
  }

  getAllTypes (): DynamicTypeTransformerAbstract[] {
    return Array.from(this.types.values())
  }
}
