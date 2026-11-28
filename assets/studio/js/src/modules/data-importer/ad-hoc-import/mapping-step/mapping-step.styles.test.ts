/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useStyles } from './mapping-step.styles'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ createStyles: (build: unknown) => build }))

type Build = (theme: { token: Record<string, unknown>, css: typeof String.raw }) => Record<string, string>

describe('the progress bar of the mapping summary', () => {
  // every token reads as its own name, nested ones as their path
  const token = (path: string): unknown => new Proxy({}, {
    get: (_, key) => key === Symbol.toPrimitive ? () => path : token(`${path}.${String(key)}`)
  })
  const styles = (useStyles as unknown as Build)({
    token: new Proxy({}, { get: (_, key) => key === 'fontSize' || key === 'lineHeight' || String(key).startsWith('padding') || String(key).startsWith('margin') ? 1 : token(String(key)) }),
    css: String.raw
  })

  // Studio's disabled text is 0.55 black, much darker than the light grey of the design's not-imported part
  it('draws the not-imported part in the light border grey, not the disabled text colour', () => {
    expect(styles.barSkipped).toMatch(/background: colorBorder;/)
    expect(styles.barSkipped).not.toMatch(/colorTextDisabled/)
  })

  it('draws the mapped part in the success colour on the lighter track', () => {
    expect(styles.barMapped).toMatch(/background: colorSuccess;/)
    expect(styles.bar).toMatch(/background: colorBorderSecondary;/)
  })
})
