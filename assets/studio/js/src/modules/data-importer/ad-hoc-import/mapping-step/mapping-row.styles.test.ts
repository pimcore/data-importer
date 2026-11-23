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
})

describe('the Skip button of a mapping row', () => {
  const styles = (useStyles as unknown as Build)({
    token: { colorTextDescription: 'description-colour', Colors: { Brand: { Warning: { colorWarningText: 'dark-warning' } } } },
    css: String.raw
  })

  // as compact as the design's, its text at the contrast of 'Choose field'
  it('is 28px high with 8px padding and readable text', () => {
    expect(styles.skipButton).toMatch(/height: 28px;/)
    expect(styles.skipButton).toMatch(/padding-inline: 8px;/)
    expect(styles.skipButton).toMatch(/color: description-colour;/)
  })
})

describe('the target button of a mapping row', () => {
  const styles = (useStyles as unknown as Build)({
    token: { Colors: { Brand: { Warning: { colorWarningText: 'dark-warning' } } } },
    css: String.raw
  })

  // as the design: the type icon 10px before the field, the field in bold
  it('keeps the icon 10px from a bold field name', () => {
    expect(styles.fieldButton).toMatch(/gap: 10px;/)
    expect(styles.fieldName).toMatch(/font-weight: 700;/)
  })
})
