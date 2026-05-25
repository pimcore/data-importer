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
import { DynamicTypeInterpreterAbstract } from "./dynamic-type-interpreter-abstract";

@injectable()
export class DynamicTypeInterpreterRegistry {
  private readonly types = new Map<string, DynamicTypeInterpreterAbstract>()

  registerDynamicType (type: DynamicTypeInterpreterAbstract): void {
    this.types.set(type.id, type)
  }

  getDynamicType (id: string): DynamicTypeInterpreterAbstract | undefined {
    return this.types.get(id)
  }

  getAllTypes (): DynamicTypeInterpreterAbstract[] {
    return Array.from(this.types.values())
  }
}