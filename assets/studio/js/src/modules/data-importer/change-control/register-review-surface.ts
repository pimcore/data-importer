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
import { isNil } from 'lodash'
import type * as ChangeControl from '@pimcore/change-control-bundle/sdk'
import { ImportConfigReviewSurface } from './import-config-review-surface'

/**
 * Change Control binds this at onInit. Types only from the package — the review lane is an
 * optional installation, and a value import would make loading this module depend on it.
 */
const REVIEW_SURFACE_REGISTRY_ID: typeof ChangeControl.REVIEW_SURFACE_REGISTRY_ID = 'ChangeControl/Review/SurfaceRegistry'

/** the subject type ImportConfigSubjectHandler registers under */
const SUBJECT_TYPE = 'data-importer-config'

/**
 * Registers the importer's review surface, if Change Control is installed at all. Resolving
 * an unbound id throws, which is the only way to ask — and a missing review lane is a
 * perfectly normal installation, not an error.
 */
export function registerChangeControlReviewSurface (): void {
  // every module's onInit runs synchronously, so a macrotask is after all of them
  setTimeout(() => {
    let registry: ChangeControl.ReviewSurfaceRegistry | undefined
    try {
      registry = container.get<ChangeControl.ReviewSurfaceRegistry>(REVIEW_SURFACE_REGISTRY_ID)
    } catch {
      return
    }

    if (isNil(registry)) {
      return
    }

    // a dynamic type is read for its id and its component, so the entry is the pair itself;
    // ReviewSurfaceType is a value, and constructing it would pull the remote in
    registry.registerDynamicType({ id: SUBJECT_TYPE, component: ImportConfigReviewSurface })
  }, 0)
}
