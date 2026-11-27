/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

export type IdleStatusKey =
  | 'data-importer.execution.status.stopped'
  | 'data-importer.execution.status.finished'
  | 'data-importer.execution.status.not-running'

export const getIdleStatusKey = (stoppedAfterError: boolean, hasCompleted: boolean): IdleStatusKey => {
  if (stoppedAfterError) {
    return 'data-importer.execution.status.stopped'
  }

  return hasCompleted
    ? 'data-importer.execution.status.finished'
    : 'data-importer.execution.status.not-running'
}
