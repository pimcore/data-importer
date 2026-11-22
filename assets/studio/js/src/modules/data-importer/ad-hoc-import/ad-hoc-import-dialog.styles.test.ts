/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useStyles } from './ad-hoc-import-dialog.styles'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ createStyles: (build: unknown) => build }))

type Build = (theme: { token: Record<string, unknown>, css: typeof String.raw }) => Record<string, string>

describe('the dialog header', () => {
  const styles = (useStyles as unknown as Build)({
    token: {
      margin: 16,
      padding: 16,
      paddingLG: 24,
      lineWidth: 1,
      colorBorderSecondary: 'light-grey',
      fontSizeLG: 14,
      fontSizeSM: 12,
      fontWeightStrong: 700,
      colorPrimary: 'purple',
      colorPrimaryBg: 'light-purple'
    },
    css: String.raw
  })

  it('sets the steps apart from the title and closes the header with a line across the dialog', () => {
    expect(styles.header).toMatch(/margin: 16px -24px 16px;/)
    expect(styles.header).toMatch(/padding: 0 24px 16px;/)
    expect(styles.header).toMatch(/border-bottom: 1px solid light-grey;/)
  })

  it('writes the step titles at the large font size and the current one in bold', () => {
    expect(styles.steps).toMatch(/max-width: 720px;/)
    expect(styles.steps).toMatch(/\.ant-steps-item-title \{\s*font-size: 14px;/)
    expect(styles.steps).toMatch(/\.ant-steps-item-process \.ant-steps-item-title \{\s*font-weight: 700;/)
  })

  it('writes the step numbers at the small font size', () => {
    expect(styles.steps).toMatch(/\.ant-steps-item-icon \{\s*font-size: 12px;\s*line-height: 1;/)
    expect(styles.steps).toMatch(/\.ant-steps-item-icon \.ant-steps-icon \{\s*font-size: inherit;\s*line-height: inherit;/)
  })

  it('draws a finished step as a light circle with the check in the primary colour', () => {
    expect(styles.steps).toMatch(/\.ant-steps-item-finish \.ant-steps-item-icon \{\s*background-color: light-purple;\s*border-color: light-purple;/)
    expect(styles.steps).toMatch(/\.ant-steps-item-finish \.ant-steps-item-icon \.ant-steps-icon \{\s*color: purple !important;/)
  })
})
