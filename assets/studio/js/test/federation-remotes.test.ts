/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { readFileSync } from 'node:fs'
import path from 'node:path'

// The federation remotes decide which other Studio plugins must be present for this one to load at
// all: a required remote that is missing makes Studio reject the whole Data Importer plugin, so its
// type disappears from the Data Hub "New" menu. Change Control is an optional peer and must stay so.
const config = readFileSync(path.resolve(__dirname, '../../rsbuild.config.ts'), 'utf8')

const remoteCalls = (): Record<string, string> => {
  const calls: Record<string, string> = {}
  for (const match of config.matchAll(/createDynamicRemote\(\s*'([^']+)'\s*(?:,\s*([^)]+?))?\s*\)/g)) {
    calls[match[1]] = (match[2] ?? '').trim()
  }
  return calls
}

describe('federation remotes', () => {
  it('loads without Change Control installed', () => {
    expect(remoteCalls().pimcore_change_control_bundle).toBe('true')
  })

  it('requires only Studio and Data Hub', () => {
    const required = Object.entries(remoteCalls())
      .filter(([, optional]) => optional !== 'true')
      .map(([bundle]) => bundle)
      .sort()

    expect(required).toEqual(['pimcore_datahub_bundle', 'pimcore_studio_ui_bundle'])
  })
})
