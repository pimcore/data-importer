/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import JSDOMEnvironment from 'jest-environment-jsdom'

// jsdom lacks browser APIs the components use: structuredClone, crypto.randomUUID, and matchMedia (antd-style reads it on import)
export default class DomEnvironment extends JSDOMEnvironment {
  constructor (...args: ConstructorParameters<typeof JSDOMEnvironment>) {
    super(...args)

    this.global.structuredClone = structuredClone
    Object.defineProperty(this.global, 'crypto', { value: crypto })
    this.global.matchMedia = (query: string): MediaQueryList => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => undefined,
      removeListener: () => undefined,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      dispatchEvent: () => false
    })
  }
}
