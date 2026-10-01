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

// Studio is a federated remote at runtime; tests mock what they use from it, one stub per entry
const stubs = fileURLToPath(new URL('./js/test/__mocks__/studio-ui-bundle', import.meta.url))

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['js/test/**/*.test.{ts,tsx}'],
    alias: [
      { find: /^@pimcore\/studio-ui-bundle\/(.+)$/, replacement: `${stubs}/$1.ts` }
    ]
  }
})
