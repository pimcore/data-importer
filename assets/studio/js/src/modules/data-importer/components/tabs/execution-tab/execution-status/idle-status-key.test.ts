/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { getIdleStatusKey } from './idle-status-key'

describe('getIdleStatusKey', () => {
  it('names an import that stopped after an error, even when items were processed', () => {
    expect(getIdleStatusKey(true, true)).toBe('data-importer.execution.status.stopped')
    expect(getIdleStatusKey(true, false)).toBe('data-importer.execution.status.stopped')
  })

  it('keeps the existing texts for an import that ran through or never ran', () => {
    expect(getIdleStatusKey(false, true)).toBe('data-importer.execution.status.finished')
    expect(getIdleStatusKey(false, false)).toBe('data-importer.execution.status.not-running')
  })
})
