/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useStyles } from './preview-step.styles'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ createStyles: (build: unknown) => build }))

type Build = (theme: { token: Record<string, unknown>, css: typeof String.raw }) => Record<string, string>

describe('the preview grid', () => {
  const styles = (useStyles as unknown as Build)({
    token: {
      red7: 'dark-red',
      colorErrorText: 'light-red',
      colorWarningText: 'light-gold',
      colorTextTertiary: 'grey',
      Colors: { Brand: { Warning: { colorWarningText: 'dark-gold' } } }
    },
    css: String.raw
  })

  // the lighter text tokens stay below 4.5:1 on the tinted rows
  it('writes the message of a skipped row in the dark red the tinted row stays readable on', () => {
    expect(styles.error).toMatch(/color: dark-red;/)
  })

  it('writes the message of a row imported without its invalid values in Studio\'s warning text colour', () => {
    expect(styles.warning).toMatch(/color: dark-gold;/)
  })

  it('writes the row number in the tertiary text colour', () => {
    expect(styles.rowNumber).toMatch(/color: grey;/)
  })
})
