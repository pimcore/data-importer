/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Re-exports from the refactored mapping-step/ subfolder.
// External consumers keep importing from './mapping-step' as before.
export { MappingStep } from './mapping-step/index'
export type { MappingStepProps } from './mapping-step/index'
