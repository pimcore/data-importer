/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from '@pimcore/studio-ui-bundle/app'

export const ROW_ERROR_MARK = 'ad-hoc-import-row-error'
export const ROW_WARNING_MARK = 'ad-hoc-import-row-warning'

export const useStyles = createStyles(({ token, css }) => ({
  counts: css`
    display: flex;
    gap: ${token.marginSM}px;
    margin-bottom: ${token.margin}px;
  `,
  count: css`
    flex: 1 1 0;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    padding: ${token.paddingXS}px ${token.paddingSM}px;
    display: flex;
    flex-direction: column;
  `,
  countWithErrors: css`
    border-color: ${token.colorErrorBorder};
    background: ${token.colorErrorBg};
  `,
  number: css`
    font-size: ${token.fontSizeHeading4}px;
    line-height: ${token.lineHeightHeading4};
    font-weight: ${token.fontWeightStrong};
  `,
  label: css`
    color: ${token.colorTextSecondary};
  `,
  checked: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    margin-bottom: ${token.marginXS}px;
  `,
  checkedTitle: css`
    font-weight: ${token.fontWeightStrong};
  `,
  hint: css`
    color: ${token.colorTextTertiary};
    flex-grow: 1;
  `,
  // a cell of one's own loses the grid's padding and its vertical centering; a long value wraps, as the board's message does
  cell: css`
    display: block;
    width: 100%;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
    line-height: ${token.lineHeight};
    white-space: normal;
    overflow-wrap: anywhere;
  `,
  matchValue: css`
    font-weight: ${token.fontWeightStrong};
  `,
  rowNumber: css`
    color: ${token.colorTextTertiary};
  `,
  // the error text tokens fail AA contrast on the tinted rows, these darker shades of the same hues pass
  error: css`
    color: ${token.red7};
  `,
  warning: css`
    color: ${token.Colors.Brand.Warning.colorWarningText};
  `,
  // a body of its own height that scrolls, so the footer stays in view on a small screen
  table: css`
    height: clamp(160px, calc(100vh - 443px), 430px);
    overflow: auto;
    border: ${token.lineWidth}px solid ${token.colorBorderContainer};
    border-radius: ${token.borderRadius}px;
  `,
  // Studio's grid takes no class per row, a row is tinted through what its cells hold;
  // the board separates rows only, and its rows are as high as one line of text
  grid: css`
    && .ant-table-cell {
      border-left-width: 0;
      border-right-width: 0;
    }

    && .ant-table-thead .ant-table-cell {
      border-top: 0 !important;
    }

    && .ant-table-row {
      height: auto;
    }

    tr:has(.${ROW_ERROR_MARK}) > td {
      background: ${token.colorErrorBg};
    }

    tr:has(.${ROW_WARNING_MARK}) > td {
      background: ${token.colorWarningBg};
    }
  `,
  pagination: css`
    margin-top: ${token.marginXS}px;

    // the board's page field and page-size select are narrower than the pager's defaults
    && .ant-pagination.ant-pagination-simple .ant-pagination-simple-pager input {
      width: 38px;
      font-size: ${token.fontSize}px;
    }

    && .ant-select {
      width: 94px !important;
    }
  `,
  total: css`
    padding-right: ${token.paddingXS}px;
    border-right: ${token.lineWidth}px solid ${token.colorBorderContainer};
    white-space: nowrap;
  `
}))
