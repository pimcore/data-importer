/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Re-exports from the refactored data-source-step/ subfolder.
// External consumers keep importing from './data-source-step' as before.
export { DataSourceStep } from './data-source-step/index'
export type { DataSourceStepProps } from './data-source-step/index'
