/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import createCache from '@emotion/cache'
import { serializeStyles } from '@emotion/serialize'

type StyleFactory = (utils: { token: Record<string, string>, css: (strings: TemplateStringsArray, ...values: unknown[]) => string }) => Record<string, string>

let mockStyleFactory: StyleFactory | undefined

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  createStyles: (factory: StyleFactory) => {
    mockStyleFactory = factory
    return () => ({ styles: {} })
  },
  useTranslation: () => ({ t: (key: string) => key })
}))

// emotion compiles the label's rule as Studio's style instance does, so the CSS is what the page gets
const labelCss = (): string => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  require('./use-import-file-menu-item')
  const styles = mockStyleFactory!({
    token: { purple1: '#f9f0ff', purple9: '#22075e' },
    css: (strings, ...values) => String.raw({ raw: strings }, ...values)
  })
  const serialized = serializeStyles([styles.label])
  const cache = createCache({ key: 'test' })

  return cache.insert(`.test-${serialized.name}`, serialized, cache.sheet, false) as string
}

describe('the menu entry hover', () => {
  it('reaches the menu entry that holds the label, hovered or active', () => {
    const css = labelCss()

    expect(css).toMatch(/(^|})\.ant-dropdown-menu \.ant-dropdown-menu-item:has\(\.test-\w+\):hover\{[^}]*background-color:#f9f0ff!important;color:#22075e;font-weight:700;/)
    expect(css).toMatch(/(^|})\.ant-dropdown-menu \.ant-dropdown-menu-item-active:has\(\.test-\w+\)\{[^}]*background-color:#f9f0ff!important/)
    expect(css).not.toContain('&')
  })
})
