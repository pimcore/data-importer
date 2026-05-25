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
import { DynamicTypeLoaderAbstract } from './dynamic-type-loader-abstract'

@injectable()
export class DynamicTypeLoaderRegistry {
  private readonly types = new Map<string, DynamicTypeLoaderAbstract>()

  registerDynamicType (type: DynamicTypeLoaderAbstract): void {
    this.types.set(type.id, type)
  }

  getDynamicType (id: string): DynamicTypeLoaderAbstract | undefined {
    return this.types.get(id)
  }

  getAllTypes (): DynamicTypeLoaderAbstract[] {
    return Array.from(this.types.values())
  }
}
