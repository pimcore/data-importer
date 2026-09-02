/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import * as studioApp from '@pimcore/studio-ui-bundle/modules/app'

type IsBundleActive = (bundleName: string) => boolean

// The isBundleActive helper only exists in studio-ui-bundle >= 2025.4.14
// on the 2025.4 LTS line, so it is resolved at runtime. On an older host
// it is undefined and every bundle counts as active - the previous
// behavior, where the import-logs tab was always offered.
const isBundleActiveHelper: IsBundleActive | undefined =
  (studioApp as Record<string, unknown>).isBundleActive as IsBundleActive | undefined

export const isBundleActive = (bundleName: string): boolean =>
  isBundleActiveHelper === undefined || isBundleActiveHelper(bundleName)
