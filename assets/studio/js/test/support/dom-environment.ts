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

// jsdom lacks browser APIs the components use: structuredClone, crypto.randomUUID, MessageChannel (antd's Form notifies its watchers
// through one) and matchMedia (antd-style reads it on import)
// Node's own channel keeps a port open per message, so Jest would never exit; a timer delivers the message instead
class TimerMessagePort {
  onmessage: ((event: { data: unknown }) => void) | null = null
  other?: TimerMessagePort

  postMessage (data: unknown): void {
    setTimeout(() => { this.other?.onmessage?.({ data }) }, 0)
  }
}

class TimerMessageChannel {
  readonly port1 = new TimerMessagePort()
  readonly port2 = new TimerMessagePort()

  constructor () {
    this.port1.other = this.port2
    this.port2.other = this.port1
  }
}

export default class DomEnvironment extends JSDOMEnvironment {
  constructor (...args: ConstructorParameters<typeof JSDOMEnvironment>) {
    super(...args)

    this.global.structuredClone = structuredClone
    Object.defineProperty(this.global, 'MessageChannel', { value: TimerMessageChannel })
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
