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
import { DynamicTypeRegistryAbstract } from '@pimcore/studio-ui-bundle/modules/element'
import { type DynamicTypeTransformerAbstract } from './dynamic-type-transformer-abstract'

@injectable()
export class DynamicTypeTransformerRegistry extends DynamicTypeRegistryAbstract<DynamicTypeTransformerAbstract> {
  /**
   * @deprecated Use {@link getDynamicTypes} instead. Kept as an alias for backwards compatibility.
   */
  getAllTypes (): DynamicTypeTransformerAbstract[] {
    return this.getDynamicTypes()
  }
}
