/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useStyles } from './mapping-row.styles'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ createStyles: (build: unknown) => build }))

type Build = (theme: { token: Record<string, unknown>, css: typeof String.raw }) => Record<string, string>

describe('the buttons of a mapping row', () => {
  const styles = (useStyles as unknown as Build)({
    token: { colorPrimaryBorder: 'focus-colour', Colors: { Brand: { Warning: { colorWarningText: 'dark-warning' } } } },
    css: String.raw
  })

  // `all: unset` takes away the browser's own outline
  it.each(['fieldButton', 'suggestion'])('%s shows where the keyboard is', (name) => {
    expect(styles[name]).toMatch(/&:focus-visible\s*{\s*outline: 2px solid focus-colour;/)
  })
})

describe('the field of a mapping row', () => {
  const styles = (useStyles as unknown as Build)({
    token: { Colors: { Brand: { Warning: { colorWarningText: 'dark-warning' } } } },
    css: String.raw
  })

  // the group path gives way first; the field's name is shown whole
  it('never cuts the field\'s name, only its group path', () => {
    expect(styles.fieldName).toMatch(/flex: none;/)
    expect(styles.fieldName).not.toMatch(/text-overflow|overflow: hidden/)
    expect(styles.crumb).toMatch(/flex: 1 1 0;/)
    expect(styles.crumb).toMatch(/text-overflow: ellipsis;/)
  })

  it('writes the transformation tag in the dark warning text', () => {
    expect(styles.needsTransformation).toMatch(/color: dark-warning;/)
  })
})
