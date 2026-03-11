/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Re-exports from the refactored processing-settings-step/ subfolder.
// External consumers keep importing from './processing-settings-step' as before.
export { ProcessingSettingsStep } from './processing-settings-step/index'
export type { ProcessingSettingsStepProps } from './processing-settings-step/index'
