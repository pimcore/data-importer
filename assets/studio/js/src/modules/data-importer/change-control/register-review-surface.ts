/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container } from '@pimcore/studio-ui-bundle'
import { ImportConfigReviewSurface } from './import-config-review-surface'

/** Change Control binds this at onInit; the id is its published contract, not an import. */
const REVIEW_SURFACE_REGISTRY_ID = 'ChangeControl/Review/SurfaceRegistry'

/** the subject type ImportConfigSubjectHandler registers under */
const SUBJECT_TYPE = 'data-importer-config'

interface SurfaceRegistry {
  register: (key: string, surface: unknown) => void
}

/**
 * Registers the importer's review surface, if Change Control is installed at all. Resolving
 * an unbound id throws, which is the only way to ask — and a missing review lane is a
 * perfectly normal installation, not an error.
 */
export function registerChangeControlReviewSurface (): void {
  // every module's onInit runs synchronously, so a macrotask is after all of them
  setTimeout(() => {
    let registry: SurfaceRegistry | undefined
    try {
      registry = container.get<SurfaceRegistry>(REVIEW_SURFACE_REGISTRY_ID)
    } catch {
      return
    }

    if (typeof registry?.register === 'function') {
      registry.register(SUBJECT_TYPE, ImportConfigReviewSurface)
    }
  }, 0)
}
