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
      colorBorderContainer: 'light-grey',
      lineWidth: 1,
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

  it('separates rows only, as the board does, and lets a long value wrap instead of cutting it off', () => {
    expect(styles.grid).toMatch(/&& \.ant-table-cell \{\s*border-left-width: 0;\s*border-right-width: 0;/)
    expect(styles.grid).toMatch(/&& \.ant-table-row \{\s*height: auto;/)
    expect(styles.cell).toMatch(/white-space: normal;/)
    expect(styles.cell).toMatch(/overflow-wrap: anywhere;/)
    expect(styles.cell).not.toMatch(/text-overflow: ellipsis;/)
  })

  it('draws a line under the header and no split lines between its column titles, as the board does', () => {
    expect(styles.grid).toMatch(/&& \.ant-table-thead \.ant-table-cell \{[^}]*border-bottom: 1px solid light-grey !important;/)
    expect(styles.grid).toMatch(/&& \.ant-table-thead > tr > th:not\(:last-child\)::before \{\s*background-color: transparent !important;/)
  })

  // a body that follows its rows pushes the footer below a 720px high screen
  it('gives the table a body of its own height that scrolls', () => {
    expect(styles.table).toMatch(/height: clamp\(160px, calc\(100vh - 443px\), 430px\);/)
    expect(styles.table).toMatch(/overflow: auto;/)
    expect(styles.table).toMatch(/border: 1px solid light-grey;/)
  })

  it('sets the total apart from the pager with a line and narrows its page field and page-size select', () => {
    expect(styles.total).toMatch(/border-right: 1px solid light-grey;/)
    expect(styles.pagination).toMatch(/input \{\s*width: 38px;/)
    expect(styles.pagination).toMatch(/\.ant-select \{\s*width: 94px !important;/)
  })
})
