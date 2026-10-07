/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

// Studio and Data Hub are federated remotes at runtime; tests mock what they use from them, one stub per entry
const stubs = fileURLToPath(new URL('./js/test/__mocks__/studio-ui-bundle', import.meta.url))
const dataHubStub = fileURLToPath(new URL('./js/test/__mocks__/data-hub/index.ts', import.meta.url))

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['js/test/**/*.test.{ts,tsx}'],
    alias: [
      { find: /^@pimcore\/studio-ui-bundle$/, replacement: `${stubs}/index.ts` },
      { find: /^@pimcore\/studio-ui-bundle\/(.+)$/, replacement: `${stubs}/$1.ts` },
      { find: /^@pimcore\/data-hub$/, replacement: dataHubStub }
    ],
    // files no test loads count as not run, so they need the stubs above to resolve
    coverage: {
      include: ['js/src/**/*.{ts,tsx}']
    }
  }
})
