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
import { type DynamicTypeInterpreterAbstract } from './dynamic-type-interpreter-abstract'

@injectable()
export class DynamicTypeInterpreterRegistry extends DynamicTypeRegistryAbstract<DynamicTypeInterpreterAbstract> {}
